<?php

namespace App\Services;

use App\Models\Client;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use Illuminate\Support\Facades\DB;

class SavingsService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Open a new savings account.
     */
    public function openAccount(array $data): SavingsAccount
    {
        return DB::transaction(function () use ($data) {
            return SavingsAccount::create([
                'client_id'      => $data['client_id'],
                'branch_id'      => $data['branch_id'] ?? Client::withoutGlobalScopes()->find($data['client_id'])?->branch_id,
                'product_id'     => $data['product_id'],
                'account_number' => $this->generateAccountNumber(),
                'balance'        => 0,
                'status'         => 'active',
                'opened_date'    => $data['opened_date'] ?? today()->toDateString(),
                'created_by'     => auth()->id(),
            ]);
        });
    }

    /**
     * Deposit funds into a savings account.
     */
    public function deposit(SavingsAccount $account, float $amount, string $date, string $description = 'Deposit', ?string $reference = null, ?int $paymentSourceAccountId = null): SavingsTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Deposit amount must be positive.');
        }

        return DB::transaction(function () use ($account, $amount, $date, $description, $reference, $paymentSourceAccountId) {
            $product     = $account->product;
            $balBefore   = $this->balanceAsOf($account, $date);
            $balAfter    = $balBefore + $amount;

            // Post journal: DR Cash/Bank, CR Savings Liability
            $transaction = $this->accounting->post(
                $date,
                "Savings deposit - {$account->account_number}",
                [
                    [
                        'account_id'  => $paymentSourceAccountId ?? $this->getCashAccount(),
                        'debit'       => $amount,
                        'credit'      => 0,
                        'description' => $description,
                    ],
                    [
                        'account_id'  => $product->savings_liability_account_id,
                        'debit'       => 0,
                        'credit'      => $amount,
                        'description' => "Savings liability - {$account->account_number}",
                    ],
                ],
                'savings',
                $account->id,
                $reference ?: null
            );

            $account->update(['balance' => $balAfter]);

            return SavingsTransaction::create([
                'savings_account_id'      => $account->id,
                'transaction_type'        => 'deposit',
                'amount'                  => $amount,
                'balance_before'          => $balBefore,
                'balance_after'           => $balAfter,
                'transaction_date'        => $date,
                'reference'               => $reference ?? $transaction->reference,
                'description'             => $description,
                'payment_source_account_id' => $paymentSourceAccountId,
                'transaction_id'          => $transaction->id,
                'created_by'              => auth()->id(),
            ]);
        });
    }

    /**
     * Withdraw funds from a savings account.
     */
    public function withdraw(SavingsAccount $account, float $amount, string $date, string $description = 'Withdrawal', ?string $reference = null, ?float $fee = null, ?int $paymentSourceAccountId = null): SavingsTransaction
    {
        $product    = $account->product;
        $minBalance = $product->minimum_balance ?? 0;
        $fee        = $fee ?? ($product->withdrawal_fee ?? 0);
        $total      = $amount + $fee;

        // Validate against the balance that existed on the transaction date, not today's balance.
        $balAsOfDate = $this->balanceAsOf($account, $date);
        if (($balAsOfDate - $total) < $minBalance) {
            throw new \InvalidArgumentException(
                "Insufficient balance as of {$date}. Available: " . number_format(max(0, $balAsOfDate - $minBalance), 2)
            );
        }

        return DB::transaction(function () use ($account, $amount, $fee, $total, $date, $description, $reference, $product, $balAsOfDate, $paymentSourceAccountId) {
            $balBefore = $balAsOfDate;
            $balAfter  = $balBefore - $total;

            $lines = [
                [
                    'account_id'  => $product->savings_liability_account_id,
                    'debit'       => $amount,
                    'credit'      => 0,
                    'description' => "Savings withdrawal - {$account->account_number}",
                ],
                [
                    'account_id'  => $paymentSourceAccountId ?? $this->getCashAccount(),
                    'debit'       => 0,
                    'credit'      => $amount,
                    'description' => $description,
                ],
            ];

            if ($fee > 0) {
                $lines[] = [
                    'account_id'  => $product->savings_liability_account_id,
                    'debit'       => $fee,
                    'credit'      => 0,
                    'description' => 'Withdrawal fee',
                ];
                $lines[] = [
                    'account_id'  => $this->getFeeIncomeAccount(),
                    'debit'       => 0,
                    'credit'      => $fee,
                    'description' => 'Withdrawal fee income',
                ];
            }

            $transaction = $this->accounting->post(
                $date,
                "Savings withdrawal - {$account->account_number}",
                $lines,
                'savings',
                $account->id,
                $reference ?: null
            );

            $account->update(['balance' => $balAfter]);

            return SavingsTransaction::create([
                'savings_account_id'      => $account->id,
                'transaction_type'        => 'withdrawal',
                'amount'                  => $total,
                'balance_before'          => $balBefore,
                'balance_after'           => $balAfter,
                'transaction_date'        => $date,
                'reference'               => $reference ?? $transaction->reference,
                'description'             => $description,
                'payment_source_account_id' => $paymentSourceAccountId,
                'transaction_id'          => $transaction->id,
                'created_by'              => auth()->id(),
            ]);
        });
    }

    /**
     * Transfer from one savings account to another.
     */
    public function transfer(SavingsAccount $from, SavingsAccount $to, float $amount, string $date, ?string $reference = null, ?float $fee = null): array
    {
        return DB::transaction(function () use ($from, $to, $amount, $date, $reference, $fee) {
            $debit  = $this->withdraw($from, $amount, $date, "Transfer to {$to->account_number}", $reference, $fee);
            $credit = $this->deposit($to, $amount, $date, "Transfer from {$from->account_number}", $reference);
            return [$debit, $credit];
        });
    }

    /**
     * Post interest to a savings account.
     *
     * Interest is calculated as the SUM of daily accruals over the period:
     *   each day's closing balance × (annual_rate / 100 / 365)
     *
     * This means a monthly posting equals the total of all daily interest
     * that would have accrued during the month, correctly reflecting any
     * deposits or withdrawals made during the period.
     */
    public function postInterest(SavingsAccount $account, string $date): ?SavingsTransaction
    {
        $product = $account->product;
        if ($product->interest_rate <= 0) return null;
        if ($account->status !== 'active')  return null;

        // Prevent posting on the same date twice (compare as date strings)
        $lastDate = $account->last_interest_date
            ? \Carbon\Carbon::parse($account->last_interest_date)->toDateString()
            : null;
        if ($lastDate && $lastDate === $date) {
            throw new \InvalidArgumentException(
                "Interest has already been posted for {$date} on this account."
            );
        }

        $periodEnd = \Carbon\Carbon::parse($date);

        // Period start: day after last posting if it exists,
        // otherwise the earliest of (opened_date, first transaction date).
        // This handles backdated deposits that pre-date the account's opened_date.
        if ($lastDate) {
            $periodStart = \Carbon\Carbon::parse($lastDate)->addDay();
        } else {
            $firstTxDate = SavingsTransaction::where('savings_account_id', $account->id)
                ->orderBy('transaction_date')->orderBy('id')
                ->value('transaction_date');
            $openDate    = \Carbon\Carbon::parse($account->opened_date);
            $periodStart = $firstTxDate
                ? \Carbon\Carbon::parse(min($account->opened_date, $firstTxDate))
                : $openDate;
        }

        // Safety: if period start is after the posting date, nothing to calculate
        if ($periodStart->gt($periodEnd)) {
            throw new \InvalidArgumentException(
                "No activity before {$date} on this account. First transaction must exist on or before the posting date."
            );
        }

        $dailyRate = (float) $product->interest_rate / 100 / 365;

        // Sum daily interest: for each day in the period use the closing balance of that day
        $totalInterest = 0.0;
        $cursor        = $periodStart->copy();

        while ($cursor->lte($periodEnd)) {
            $dayBalance = $this->balanceAsOf($account, $cursor->toDateString());
            if ($dayBalance > 0) {
                $totalInterest += $dayBalance * $dailyRate;
            }
            $cursor->addDay();
        }

        $totalInterest = round($totalInterest, 2);
        if ($totalInterest <= 0) return null;

        $periodLabel = $periodStart->format('d M Y') . ' – ' . $periodEnd->format('d M Y');
        $txn = $this->deposit(
            $account,
            $totalInterest,
            $date,
            "Interest credit ({$periodLabel})",
            'INT-' . $account->account_number . '-' . now()->format('YmdHis')
        );
        $account->update(['last_interest_date' => $date]);

        return $txn;
    }

    /**
     * Return the savings account balance as of a given date,
     * derived from the savings_transactions ledger.
     * Falls back to 0 if no transactions exist on or before that date.
     */
    protected function balanceAsOf(SavingsAccount $account, string $date): float
    {
        $last = SavingsTransaction::where('savings_account_id', $account->id)
            ->where('transaction_date', '<=', $date)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $last ? (float) $last->balance_after : 0.0;
    }

    protected function getCashAccount(): int
    {
        // Returns default cash/bank account ID - configured in chart of accounts
        $account = \App\Models\Account::where('account_code', '1001')->first();
        return $account?->id ?? 1;
    }

    protected function getFeeIncomeAccount(): int
    {
        $account = \App\Models\Account::where('account_code', '4002')->first();
        return $account?->id ?? 1;
    }

    protected function generateAccountNumber(): string
    {
        $prefix = 'SAV';
        $year   = now()->format('y');
        $last   = SavingsAccount::count() + 1;
        return "{$prefix}{$year}" . str_pad($last, 5, '0', STR_PAD_LEFT);
    }
}
