<?php

namespace App\Services;

use App\Models\Client;
use App\Models\FixedDeposit;
use App\Models\FixedDepositProduct;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FixedDepositService
{
    public function __construct(
        protected AccountingService $accounting,
        protected \App\Services\SavingsService $savingsService,
    ) {}

    /**
     * Create a new fixed deposit.
     */
    public function createDeposit(array $data): FixedDeposit
    {
        $reference = $data['reference'] ?? null;
        return DB::transaction(function () use ($data, $reference) {
            $product    = FixedDepositProduct::findOrFail($data['product_id']);
            $principal  = $data['principal'];
            $rate       = $data['interest_rate'] ?? $product->interest_rate;
            $months     = $data['term_months'] ?? $product->term_months;
            $startDate  = Carbon::parse($data['start_date']);
            $maturity   = $startDate->copy()->addMonths($months);

            // Simple interest: P * R * T (T in years)
            $interestAmount  = $principal * ($rate / 100) * ($months / 12);
            $maturityAmount  = $principal + $interestAmount;

            $savingsAccount = isset($data['savings_account_id']) && $data['savings_account_id']
                ? SavingsAccount::findOrFail($data['savings_account_id'])
                : null;

            if ($savingsAccount && !$savingsAccount->canWithdraw($principal)) {
                throw new \InvalidArgumentException(
                    'Insufficient savings balance to fund this fixed deposit.'
                );
            }

            $deposit = FixedDeposit::create([
                'client_id'          => $data['client_id'],
                'branch_id'          => $data['branch_id'] ?? Client::withoutGlobalScopes()->find($data['client_id'])?->branch_id,
                'savings_account_id' => $savingsAccount?->id,
                'product_id'         => $data['product_id'],
                'deposit_number'     => $this->generateDepositNumber(),
                'principal'          => $principal,
                'interest_rate'      => $rate,
                'term_months'        => $months,
                'start_date'         => $startDate->toDateString(),
                'maturity_date'      => $maturity->toDateString(),
                'interest_amount'    => round($interestAmount, 2),
                'maturity_amount'    => round($maturityAmount, 2),
                'accrued_interest'   => 0,
                'status'             => 'active',
                'payment_source_account_id' => $savingsAccount ? null : ($data['payment_source_account_id'] ?? null),
                'created_by'         => auth()->id(),
            ]);

            if ($savingsAccount) {
                // Deduct from savings: DR Savings Liability, CR FD Liability (no cash)
                $savingsProduct = $savingsAccount->product;
                $balBefore      = $savingsAccount->balance;

                $placementJournal = $this->accounting->post(
                    $startDate->toDateString(),
                    "Fixed deposit placement from savings - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $savingsProduct->savings_liability_account_id,
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD placement - {$savingsAccount->account_number}",
                        ],
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $principal,
                            'description' => "FD liability - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );

                $savingsAccount->update(['balance' => $balBefore - $principal]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'withdrawal',
                    'amount'             => $principal,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balBefore - $principal,
                    'transaction_date'   => $startDate->toDateString(),
                    'reference'          => $placementJournal->reference,
                    'description'        => "Fixed deposit placement - {$deposit->deposit_number}",
                    'transaction_id'     => $placementJournal->id,
                    'created_by'         => auth()->id(),
                ]);
            } else {
                // Cash-funded: DR Cash/Bank, CR FD Liability
                $this->accounting->post(
                    $startDate->toDateString(),
                    "Fixed deposit creation - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $data['payment_source_account_id'] ?? $this->getCashAccount(),
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD receipt - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $principal,
                            'description' => "FD liability - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );
            }

            return $deposit;
        });
    }

    /**
     * Accrue interest for a fixed deposit (periodic).
     */
    public function accrueInterest(FixedDeposit $deposit, string $date): void
    {
        if ($deposit->status !== 'active') return;

        $product       = $deposit->product;
        $daysElapsed   = Carbon::parse($deposit->start_date)->diffInDays(Carbon::parse($date));
        $totalDays     = Carbon::parse($deposit->start_date)->diffInDays($deposit->maturity_date);
        $totalInterest = $deposit->interest_amount;

        $accruedToDate = $totalDays > 0
            ? round(($daysElapsed / $totalDays) * $totalInterest, 2)
            : 0;

        $newAccrual = max(0, $accruedToDate - $deposit->accrued_interest);
        if ($newAccrual <= 0) return;

        DB::transaction(function () use ($deposit, $product, $newAccrual, $date) {
            // DR Interest Expense, CR Interest Payable
            $this->accounting->post(
                $date,
                "Interest accrual - {$deposit->deposit_number}",
                [
                    [
                        'account_id'  => $product->interest_expense_account_id,
                        'debit'       => $newAccrual,
                        'credit'      => 0,
                        'description' => "Interest expense - {$deposit->deposit_number}",
                    ],
                    [
                        'account_id'  => $this->getInterestPayableAccount(),
                        'debit'       => 0,
                        'credit'      => $newAccrual,
                        'description' => "Interest payable - {$deposit->deposit_number}",
                    ],
                ],
                'fixed_deposit',
                $deposit->id
            );

            $deposit->increment('accrued_interest', $newAccrual);

            if (Carbon::parse($date)->gte($deposit->maturity_date)) {
                $deposit->update(['status' => 'matured']);
            }
        });
    }

    /**
     * Mature and pay out a fixed deposit (only on/after maturity date).
     */
    public function matureDeposit(FixedDeposit $deposit, string $date, ?string $reference = null): FixedDeposit
    {
        if (!in_array($deposit->status, ['active', 'matured'])) {
            throw new \InvalidArgumentException('Deposit cannot be matured in its current state.');
        }

        if (Carbon::parse($date)->lt($deposit->maturity_date)) {
            throw new \InvalidArgumentException(
                'Maturity payout can only be processed on or after the maturity date (' .
                $deposit->maturity_date->format('d M Y') . '). Use "Break FD" for early termination.'
            );
        }

        return DB::transaction(function () use ($deposit, $date, $reference) {
            $product        = $deposit->product;
            $principal      = $deposit->principal;
            $interest       = $deposit->interest_amount;
            $total          = $deposit->maturity_amount;
            $savingsAccount = $deposit->savingsAccount;

            if ($savingsAccount) {
                // Credit maturity amount back to savings: DR FD Liability + Interest Payable, CR Savings Liability
                $savingsProduct = $savingsAccount->product;
                $balBefore      = $savingsAccount->balance;

                $maturityJournal = $this->accounting->post(
                    $date,
                    "Fixed deposit maturity to savings - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD principal returned - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $this->getInterestPayableAccount(),
                            'debit'       => $interest,
                            'credit'      => 0,
                            'description' => "FD interest payout - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $savingsProduct->savings_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $total,
                            'description' => "FD maturity credited to {$savingsAccount->account_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );

                $savingsAccount->update(['balance' => $balBefore + $total]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $total,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balBefore + $total,
                    'transaction_date'   => $date,
                    'reference'          => $maturityJournal->reference,
                    'description'        => "Fixed deposit maturity - {$deposit->deposit_number}",
                    'transaction_id'     => $maturityJournal->id,
                    'created_by'         => auth()->id(),
                ]);
            } else {
                // Cash payout: DR FD Liability, DR Interest Payable, CR Cash
                $this->accounting->post(
                    $date,
                    "Fixed deposit maturity payout - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD principal repayment - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $this->getInterestPayableAccount(),
                            'debit'       => $interest,
                            'credit'      => 0,
                            'description' => "FD interest payout - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $this->getCashAccount(),
                            'debit'       => 0,
                            'credit'      => $total,
                            'description' => "FD maturity payout - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );
            }

            $deposit->update([
                'status'      => 'closed',
                'closed_date' => $date,
            ]);

            return $deposit->fresh();
        });
    }

    /**
     * Break a fixed deposit early — reverses accrued interest, returns principal only.
     */
    public function breakDeposit(FixedDeposit $deposit, string $breakDate, ?string $reference = null): FixedDeposit
    {
        if ($deposit->status !== 'active') {
            throw new \InvalidArgumentException('Only active deposits can be broken early.');
        }

        if (Carbon::parse($breakDate)->gte($deposit->maturity_date)) {
            throw new \InvalidArgumentException(
                'Deposit has already reached maturity. Use "Process Maturity Payout" instead.'
            );
        }

        return DB::transaction(function () use ($deposit, $breakDate, $reference) {
            $product        = $deposit->product;
            $principal      = $deposit->principal;
            $accrued        = $deposit->accrued_interest;
            $savingsAccount = $deposit->savingsAccount;

            // Reverse any accrued interest that was posted to GL
            if ($accrued > 0) {
                $this->accounting->post(
                    $breakDate,
                    "FD early break - interest reversal - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $this->getInterestPayableAccount(),
                            'debit'       => $accrued,
                            'credit'      => 0,
                            'description' => "Interest payable reversal - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $product->interest_expense_account_id,
                            'debit'       => 0,
                            'credit'      => $accrued,
                            'description' => "Interest expense reversal - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id
                );
            }

            // Return principal only (no interest)
            if ($savingsAccount) {
                $savingsProduct = $savingsAccount->product;
                $balBefore      = $savingsAccount->balance;

                $breakJournal = $this->accounting->post(
                    $breakDate,
                    "FD early break - principal return to savings - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD principal return - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $savingsProduct->savings_liability_account_id,
                            'debit'       => 0,
                            'credit'      => $principal,
                            'description' => "FD break - principal to {$savingsAccount->account_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );

                $savingsAccount->update(['balance' => $balBefore + $principal]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'deposit',
                    'amount'             => $principal,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balBefore + $principal,
                    'transaction_date'   => $breakDate,
                    'reference'          => $breakJournal->reference,
                    'description'        => "Fixed deposit early break - principal returned - {$deposit->deposit_number}",
                    'transaction_id'     => $breakJournal->id,
                    'created_by'         => auth()->id(),
                ]);
            } else {
                $this->accounting->post(
                    $breakDate,
                    "FD early break - principal cash payout - {$deposit->deposit_number}",
                    [
                        [
                            'account_id'  => $product->deposit_liability_account_id,
                            'debit'       => $principal,
                            'credit'      => 0,
                            'description' => "FD principal return - {$deposit->deposit_number}",
                        ],
                        [
                            'account_id'  => $this->getCashAccount(),
                            'debit'       => 0,
                            'credit'      => $principal,
                            'description' => "FD break cash payout - {$deposit->deposit_number}",
                        ],
                    ],
                    'fixed_deposit',
                    $deposit->id,
                    $reference
                );
            }

            $deposit->update([
                'status'           => 'broken',
                'closed_date'      => $breakDate,
                'accrued_interest' => 0,
            ]);

            return $deposit->fresh();
        });
    }

    protected function getCashAccount(): int
    {
        $account = \App\Models\Account::where('account_code', '1001')->first();
        return $account?->id ?? 1;
    }

    protected function getInterestPayableAccount(): int
    {
        $account = \App\Models\Account::where('account_code', '2003')->first();
        return $account?->id ?? 1;
    }

    protected function generateDepositNumber(): string
    {
        $prefix = 'FD';
        $year   = now()->format('Y');
        $last   = FixedDeposit::whereYear('created_at', $year)->count() + 1;
        return "{$prefix}-{$year}-" . str_pad($last, 5, '0', STR_PAD_LEFT);
    }
}
