<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Create a new loan and generate its repayment schedule.
     */
    public function createLoan(array $data): Loan
    {
        return DB::transaction(function () use ($data) {
            $product = LoanProduct::findOrFail($data['loan_product_id']);

            $loan = Loan::create([
                'loan_number'     => $this->generateLoanNumber(),
                'client_id'       => $data['client_id'],
                'loan_product_id' => $data['loan_product_id'],
                'principal'       => $data['principal'],
                'interest_rate'        => $data['interest_rate']        ?? $product->interest_rate,
                'interest_method'      => $data['interest_method']      ?? $product->interest_method,
                'repayment_frequency'  => $data['repayment_frequency']  ?? $product->repayment_frequency ?? 'monthly',
                'term_months'          => $data['term_months']          ?? $product->term_months,
                'status'          => 'pending',
                'created_by'      => auth()->id(),
                'notes'           => $data['notes'] ?? null,
            ]);

            return $loan;
        });
    }

    /**
     * Disburse a loan: set status to active, generate schedule, post journal.
     * $feeData = ['method' => 'loan'|'savings', 'savings_account_id' => int|null]
     */
    public function disburseLoan(Loan $loan, string $disbursementDate, array $feeData = []): Loan
    {
        return DB::transaction(function () use ($loan, $disbursementDate, $feeData) {
            $maturityDate         = Carbon::parse($disbursementDate)->addMonths($loan->term_months)->toDateString();
            $managementFeeRate    = (float) ($feeData['management_fee_rate'] ?? 1.5);
            $insuranceFeeRate     = (float) ($feeData['insurance_fee_rate']  ?? 1.5);
            $applicationFeeMethod = $feeData['application_fee_method'] ?? 'loan';
            $managementFeeMethod  = $feeData['management_fee_method']  ?? 'loan';
            $insuranceFeeMethod   = $feeData['insurance_fee_method']   ?? 'loan';
            // Application fee is a flat amount; rate stored as 0
            $applicationFee       = round((float) ($feeData['application_fee_amount'] ?? 0), 2);
            $applicationFeeRate   = 0;
            $managementFee        = round($loan->principal * $managementFeeRate / 100, 2);
            $insuranceFee         = round($loan->principal * $insuranceFeeRate  / 100, 2);

            // Split fees by deduction method
            $loanFees    = ($applicationFeeMethod === 'loan'    ? $applicationFee : 0)
                         + ($managementFeeMethod  === 'loan'    ? $managementFee  : 0)
                         + ($insuranceFeeMethod   === 'loan'    ? $insuranceFee   : 0);
            $savingsFees = ($applicationFeeMethod === 'savings' ? $applicationFee : 0)
                         + ($managementFeeMethod  === 'savings' ? $managementFee  : 0)
                         + ($insuranceFeeMethod   === 'savings' ? $insuranceFee   : 0);

            $anySavings = $savingsFees > 0;

            $loan->update([
                'status'                   => 'active',
                'disbursement_date'        => $disbursementDate,
                'maturity_date'            => $maturityDate,
                'outstanding_principal'    => $loan->principal,
                'application_fee'          => $applicationFee,
                'application_fee_rate'     => $applicationFeeRate,
                'application_fee_method'   => $applicationFeeMethod,
                'management_fee'           => $managementFee,
                'management_fee_rate'      => $managementFeeRate,
                'management_fee_method'    => $managementFeeMethod,
                'insurance_fee'            => $insuranceFee,
                'insurance_fee_rate'       => $insuranceFeeRate,
                'insurance_fee_method'     => $insuranceFeeMethod,
                'fee_savings_account_id'   => $anySavings ? ($feeData['savings_account_id'] ?? null) : null,
            ]);

            $this->generateSchedule($loan);
            $this->postDisbursementJournal(
                $loan, $disbursementDate,
                $applicationFee, $applicationFeeMethod,
                $managementFee,  $managementFeeMethod,
                $insuranceFee,   $insuranceFeeMethod
            );

            // Deduct savings-method fees from the savings account
            if ($anySavings && !empty($feeData['savings_account_id'])) {
                $savingsAccount = SavingsAccount::findOrFail($feeData['savings_account_id']);

                if (!$savingsAccount->canWithdraw($savingsFees)) {
                    throw new \InvalidArgumentException(
                        'Insufficient savings balance to cover fees of ' . number_format($savingsFees, 2) . '.'
                    );
                }

                $savingsProduct = $savingsAccount->product;
                $balBefore      = $savingsAccount->balance;

                $savingsLines = [
                    [
                        'account_id'  => $savingsProduct->savings_liability_account_id,
                        'debit'       => $savingsFees,
                        'credit'      => 0,
                        'description' => "Loan fees — {$savingsAccount->account_number}",
                    ],
                ];
                if ($applicationFeeMethod === 'savings' && $applicationFee > 0) {
                    $savingsLines[] = [
                        'account_id'  => $this->getApplicationFeeAccount(),
                        'debit'       => 0,
                        'credit'      => $applicationFee,
                        'description' => "Application fee — {$loan->loan_number}",
                    ];
                }
                if ($managementFeeMethod === 'savings' && $managementFee > 0) {
                    $savingsLines[] = [
                        'account_id'  => $this->getManagementFeeAccount(),
                        'debit'       => 0,
                        'credit'      => $managementFee,
                        'description' => "Management fee — {$loan->loan_number}",
                    ];
                }
                if ($insuranceFeeMethod === 'savings' && $insuranceFee > 0) {
                    $savingsLines[] = [
                        'account_id'  => $this->getInsurancePayableAccount(),
                        'debit'       => 0,
                        'credit'      => $insuranceFee,
                        'description' => "Insurance payable — {$loan->loan_number}",
                    ];
                }
                $feeJournal = $this->accounting->post(
                    $disbursementDate,
                    "Loan fees deducted from savings — {$loan->loan_number}",
                    $savingsLines,
                    'loan',
                    $loan->id
                );

                $savingsAccount->update(['balance' => $balBefore - $savingsFees]);

                SavingsTransaction::create([
                    'savings_account_id' => $savingsAccount->id,
                    'transaction_type'   => 'withdrawal',
                    'amount'             => $savingsFees,
                    'balance_before'     => $balBefore,
                    'balance_after'      => $balBefore - $savingsFees,
                    'transaction_date'   => $disbursementDate,
                    'reference'          => $feeJournal->reference,
                    'description'        => "Loan fees deducted from savings — {$loan->loan_number}",
                    'transaction_id'     => $feeJournal->id,
                    'created_by'         => auth()->id(),
                ]);
            }

            return $loan->fresh();
        });
    }

    /**
     * Generate repayment schedule (flat or reducing balance, monthly or quarterly).
     */
    public function generateSchedule(Loan $loan): void
    {
        $loan->schedules()->delete();

        $rows = $this->buildScheduleRows($loan, Carbon::parse($loan->disbursement_date));

        foreach ($rows as $row) {
            LoanSchedule::create([
                'loan_id'        => $loan->id,
                'installment_no' => $row['installment_no'],
                'due_date'       => $row['due_date'],
                'principal_due'  => $row['principal_due'],
                'interest_due'   => $row['interest_due'],
                'total_due'      => $row['total_due'],
                'balance_after'  => $row['balance_after'],
                'status'         => 'pending',
            ]);
        }

        $loan->update([
            'outstanding_interest' => $loan->schedules()->sum('interest_due'),
        ]);
    }

    /**
     * Generate a projected schedule array without saving (for pending loan preview).
     * Uses today as the assumed disbursement date.
     */
    public function previewSchedule(Loan $loan, string $fromDate = null): array
    {
        return $this->buildScheduleRows($loan, Carbon::parse($fromDate ?? today()), true);
    }

    /**
     * Core schedule builder — used by both generateSchedule and previewSchedule.
     * Returns an array of rows. If $formatDates is true dates are formatted as 'd M Y', else date strings.
     */
    public function buildScheduleRows(Loan $loan, Carbon $startDate, bool $formatDates = false): array
    {
        $principal = $loan->principal;
        $rate      = $loan->interest_rate / 100;
        $months    = $loan->term_months;
        $frequency = $loan->repayment_frequency ?? 'monthly';

        [$periods, $periodsPerYear, $step, $dueDateFor] = $this->resolveSchedulePlan($startDate, $months, $frequency);

        $balance = $principal;
        $rows    = [];

        if ($loan->interest_method === 'flat') {
            // Total interest over entire term (same regardless of frequency)
            $totalInterest   = $principal * $rate * ($months / 12);
            $perPrincipal    = $principal / $periods;
            $perInterest     = $totalInterest / $periods;
            $principalSaved  = 0;
            $interestSaved   = 0;

            for ($i = 1; $i <= $periods; $i++) {
                $balance -= $perPrincipal;

                if ($i === $periods) {
                    $pDue = round($principal - $principalSaved, 2);
                    $iDue = round($totalInterest - $interestSaved, 2);
                } else {
                    $pDue = round($perPrincipal, 2);
                    $iDue = round($perInterest, 2);
                }

                $principalSaved += $pDue;
                $interestSaved  += $iDue;
                $dueDate         = $dueDateFor($i);

                $rows[] = [
                    'installment_no'      => $i,
                    'due_date'            => $formatDates ? $dueDate->format('d M Y') : $dueDate->toDateString(),
                    'principal_due'       => $pDue,
                    'interest_due'        => $iDue,
                    'total_due'           => $pDue + $iDue,
                    'balance_after'       => round(max($balance, 0), 2),
                    // Monthly equivalent breakdown (each period split across its months)
                    'monthly_principal'   => round($pDue / $step, 2),
                    'monthly_interest'    => round($iDue / $step, 2),
                    'monthly_total'       => round(($pDue + $iDue) / $step, 2),
                    'step_months'         => $step,
                ];
            }
        } else {
            // Reducing balance — use period rate for the chosen frequency
            $periodRate = $rate / $periodsPerYear;

            if ($periodRate == 0) {
                $periodInstallment = $principal / $periods;
            } else {
                $periodInstallment = $principal * ($periodRate * pow(1 + $periodRate, $periods))
                    / (pow(1 + $periodRate, $periods) - 1);
            }

            $principalSaved = 0;

            for ($i = 1; $i <= $periods; $i++) {
                $interestDue  = $balance * $periodRate;
                $principalDue = $periodInstallment - $interestDue;

                if ($i === $periods) {
                    $pStored = round($principal - $principalSaved, 2);
                    $iStored = round($interestDue, 2);
                    $tStored = $pStored + $iStored;
                } else {
                    $pStored = round($principalDue, 2);
                    $iStored = round($interestDue, 2);
                    $tStored = round($periodInstallment, 2);
                }

                $balance -= $principalDue;
                $principalSaved += $pStored;
                $dueDate = $dueDateFor($i);

                $rows[] = [
                    'installment_no'    => $i,
                    'due_date'          => $formatDates ? $dueDate->format('d M Y') : $dueDate->toDateString(),
                    'principal_due'     => $pStored,
                    'interest_due'      => $iStored,
                    'total_due'         => $tStored,
                    'balance_after'     => round(max($balance, 0), 2),
                    'monthly_principal' => round($pStored / $step, 2),
                    'monthly_interest'  => round($iStored / $step, 2),
                    'monthly_total'     => round($tStored / $step, 2),
                    'step_months'       => $step,
                ];
            }
        }

        return $rows;
    }

    /**
     * Resolve, for a given repayment frequency and term (in months), how many
     * installments there are, the periods-per-year used to convert the annual
     * rate to a period rate, a "step" in months (only meaningful for monthly/
     * quarterly/annually - used for the quarterly "monthly equivalent" preview
     * breakdown), and a callback that returns the due date for installment $i.
     *
     * @return array{0:int,1:int,2:int,3:\Closure}
     */
    private function resolveSchedulePlan(Carbon $startDate, int $months, string $frequency): array
    {
        $termEnd = $startDate->copy()->addMonths($months);

        switch ($frequency) {
            case 'daily':
                $periods = max(1, $startDate->diffInDays($termEnd));
                return [$periods, 365, 1, fn (int $i) => $startDate->copy()->addDays($i)];

            case 'weekly':
                $periods = max(1, intdiv($startDate->diffInDays($termEnd), 7));
                return [$periods, 52, 1, fn (int $i) => $startDate->copy()->addWeeks($i)];

            case 'quarterly':
                $periods = max(1, intval($months / 3));
                return [$periods, 4, 3, fn (int $i) => $startDate->copy()->addMonths($i * 3)];

            case 'annually':
                $periods = max(1, intval($months / 12));
                return [$periods, 1, 12, fn (int $i) => $startDate->copy()->addMonths($i * 12)];

            case 'monthly':
            default:
                $periods = max(1, $months);
                return [$periods, 12, 1, fn (int $i) => $startDate->copy()->addMonths($i)];
        }
    }

    /**
     * Process a loan repayment — schedule-based allocation:
     * penalty first, then per-installment (interest → principal) in due order.
     */
    public function processRepayment(Loan $loan, array $data): LoanRepayment
    {
        return DB::transaction(function () use ($loan, $data) {
            $amount    = $data['amount'];
            $remaining = $amount;

            $penaltyPaid   = 0;
            $interestPaid  = 0;
            $principalPaid = 0;

            // 1. Penalty first
            $penaltyDue = $this->calculatePenalty($loan);
            if ($remaining > 0 && $penaltyDue > 0) {
                $penaltyPaid = min($remaining, $penaltyDue);
                $remaining  -= $penaltyPaid;
            }

            // 2. Per-installment allocation (interest → principal), earliest first
            $schedules = $loan->schedules()
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('installment_no')
                ->get();

            foreach ($schedules as $schedule) {
                if ($remaining <= 0) break;

                $iDue = $schedule->interest_due - $schedule->interest_paid;
                if ($remaining > 0 && $iDue > 0) {
                    $iApply = min($remaining, $iDue);
                    $schedule->interest_paid += $iApply;
                    $interestPaid            += $iApply;
                    $remaining               -= $iApply;
                }

                $pDue = $schedule->principal_due - $schedule->principal_paid;
                if ($remaining > 0 && $pDue > 0) {
                    $pApply = min($remaining, $pDue);
                    $schedule->principal_paid += $pApply;
                    $principalPaid            += $pApply;
                    $remaining                -= $pApply;
                }

                if (
                    abs($schedule->principal_paid - $schedule->principal_due) < 0.01 &&
                    abs($schedule->interest_paid  - $schedule->interest_due)  < 0.01
                ) {
                    $schedule->status = 'paid';
                } elseif ($schedule->principal_paid > 0 || $schedule->interest_paid > 0) {
                    $schedule->status = 'partial';
                }

                $schedule->save();
            }

            // Post journal
            $paymentSourceAccountId = isset($data['payment_source_account_id']) && is_numeric($data['payment_source_account_id'])
                ? (int) $data['payment_source_account_id']
                : null;

            $transaction = $this->postRepaymentJournal($loan, $data['payment_date'], $principalPaid, $interestPaid, $penaltyPaid, $data['reference'] ?? null, $paymentSourceAccountId);

            $repayment = LoanRepayment::create([
                'loan_id'                  => $loan->id,
                'payment_date'             => $data['payment_date'],
                'amount'                   => $amount,
                'principal_paid'           => $principalPaid,
                'interest_paid'            => $interestPaid,
                'penalty_paid'             => $penaltyPaid,
                'payment_method'           => $data['payment_method'] ?? 'direct',
                'payment_source_account_id'=> $paymentSourceAccountId,
                'reference'                => $data['reference'] ?? null,
                'received_by'              => auth()->id(),
                'transaction_id'           => $transaction->id,
                'notes'                    => $data['notes'] ?? null,
            ]);

            // Update loan outstanding balances
            $newPrincipal = $loan->outstanding_principal - $principalPaid;
            $newInterest  = $loan->outstanding_interest  - $interestPaid;
            $newPenalty   = max(0, $loan->outstanding_penalty - $penaltyPaid);

            $loan->update([
                'outstanding_principal' => max(0, $newPrincipal),
                'outstanding_interest'  => max(0, $newInterest),
                'outstanding_penalty'   => $newPenalty,
            ]);

            if ($newPrincipal <= 0.01 && $newInterest <= 0.01 && $newPenalty <= 0.01) {
                $loan->update(['status' => 'closed']);
            }

            return $repayment;
        });
    }

    /**
     * Calculate the amount required to settle the loan early as of a given date.
     * Interest is charged for every installment period that has already started:
     * period 1 starts on disbursement date, period N starts on the previous due date.
     */
    public function calculateEarlySettlement(Loan $loan, string $date): array
    {
        $principal = $loan->outstanding_principal;
        $penalty   = $this->calculatePenalty($loan);

        $schedules = $loan->schedules()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_no')
            ->get();

        $interestDue  = 0;
        $disbursedStr = $loan->disbursement_date->toDateString();

        foreach ($schedules as $index => $schedule) {
            $periodStart = $index === 0
                ? $disbursedStr
                : $schedules[$index - 1]->due_date->toDateString();

            if ($periodStart <= $date) {
                $interestDue += max(0, $schedule->interest_due - $schedule->interest_paid);
            } else {
                break;
            }
        }

        return [
            'principal' => round($principal, 2),
            'interest'  => round($interestDue, 2),
            'penalty'   => round($penalty, 2),
            'total'     => round($principal + $interestDue + $penalty, 2),
        ];
    }

    public function calculatePenaltyPublic(Loan $loan): float
    {
        return $this->calculatePenalty($loan);
    }

    /** Returns per-installment penalty amounts keyed by schedule ID. */
    public function penaltyBreakdown(Loan $loan): array
    {
        $product = $loan->product;
        if (!$product || $product->penalty_rate <= 0) return [];

        $breakdown = [];
        $overdueSchedules = $loan->schedules()
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', 'paid')
            ->get();

        foreach ($overdueSchedules as $schedule) {
            $daysOverdue   = Carbon::parse($schedule->due_date)->diffInDays(now());
            $overdueAmount = $schedule->principal_due - $schedule->principal_paid;
            $breakdown[$schedule->id] = round($overdueAmount * ($product->penalty_rate / 100) * $daysOverdue, 2);
        }

        return $breakdown;
    }

    protected function calculatePenalty(Loan $loan): float
    {
        $product = $loan->product;
        if (!$product || $product->penalty_rate <= 0) return 0;

        $overdueSchedules = $loan->schedules()
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', 'paid')
            ->get();

        $penalty = 0;
        foreach ($overdueSchedules as $schedule) {
            $daysOverdue = Carbon::parse($schedule->due_date)->diffInDays(now());
            $overdueAmount = ($schedule->principal_due - $schedule->principal_paid);
            $penalty += $overdueAmount * ($product->penalty_rate / 100) * $daysOverdue;
        }

        return round($penalty, 2);
    }

    protected function updateScheduleStatuses(Loan $loan, float $principalPaid, float $interestPaid): void
    {
        $remaining = $principalPaid;
        $remainingInterest = $interestPaid;

        $pendingSchedules = $loan->schedules()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_no')
            ->get();

        foreach ($pendingSchedules as $schedule) {
            if ($remaining <= 0 && $remainingInterest <= 0) break;

            $principalToApply = min($remaining, $schedule->principal_due - $schedule->principal_paid);
            $interestToApply  = min($remainingInterest, $schedule->interest_due - $schedule->interest_paid);

            $schedule->principal_paid += $principalToApply;
            $schedule->interest_paid  += $interestToApply;
            $remaining                -= $principalToApply;
            $remainingInterest        -= $interestToApply;

            if (
                abs($schedule->principal_paid - $schedule->principal_due) < 0.01 &&
                abs($schedule->interest_paid - $schedule->interest_due) < 0.01
            ) {
                $schedule->status = 'paid';
            } elseif ($schedule->principal_paid > 0 || $schedule->interest_paid > 0) {
                $schedule->status = 'partial';
            }

            $schedule->save();
        }
    }

    protected function postDisbursementJournal(
        Loan $loan, string $date,
        float $applicationFee = 0, string $applicationFeeMethod = 'loan',
        float $managementFee  = 0, string $managementFeeMethod  = 'loan',
        float $insuranceFee   = 0, string $insuranceFeeMethod   = 'loan'
    ): \App\Models\Transaction {
        $product   = $loan->product;
        $principal = $loan->principal;

        // Only fees deducted from the loan reduce the cash disbursed
        $loanFees = ($applicationFeeMethod === 'loan' ? $applicationFee : 0)
                  + ($managementFeeMethod  === 'loan' ? $managementFee  : 0)
                  + ($insuranceFeeMethod   === 'loan' ? $insuranceFee   : 0);

        $lines = [
            [
                'account_id'  => $this->resolveGlAccountId($product->receivable_account_id, '1101'),
                'debit'       => $principal,
                'credit'      => 0,
                'description' => "Loan receivable - {$loan->loan_number}",
            ],
            [
                'account_id'  => $this->resolveGlAccountId($product->disbursement_account_id, '1001'),
                'debit'       => 0,
                'credit'      => $principal - $loanFees,
                'description' => $loanFees > 0
                    ? "Cash disbursement (net of loan fees) - {$loan->loan_number}"
                    : "Cash disbursement - {$loan->loan_number}",
            ],
        ];

        if ($applicationFeeMethod === 'loan' && $applicationFee > 0) {
            $lines[] = [
                'account_id'  => $this->getApplicationFeeAccount(),
                'debit'       => 0,
                'credit'      => $applicationFee,
                'description' => "Application fee - {$loan->loan_number}",
            ];
        }
        if ($managementFeeMethod === 'loan' && $managementFee > 0) {
            $lines[] = [
                'account_id'  => $this->getManagementFeeAccount(),
                'debit'       => 0,
                'credit'      => $managementFee,
                'description' => "Management fee - {$loan->loan_number}",
            ];
        }
        if ($insuranceFeeMethod === 'loan' && $insuranceFee > 0) {
            $lines[] = [
                'account_id'  => $this->getInsurancePayableAccount(),
                'debit'       => 0,
                'credit'      => $insuranceFee,
                'description' => "Insurance payable - {$loan->loan_number}",
            ];
        }

        return $this->accounting->post(
            $date,
            "Loan disbursement: {$loan->loan_number}",
            $lines,
            'loan',
            $loan->id
        );
    }

    protected function getApplicationFeeAccount(): int
    {
        return Account::where('account_code', '4011')->value('id') ?? 1;
    }

    protected function getManagementFeeAccount(): int
    {
        return Account::where('account_code', '4009')->value('id') ?? 1;
    }

    protected function getInsuranceFeeAccount(): int
    {
        return Account::where('account_code', '4010')->value('id') ?? 1;
    }

    protected function getInsurancePayableAccount(): int
    {
        return Account::where('account_code', '2010')->value('id') ?? 1;
    }

    /**
     * Loan products may omit GL links; fall back to standard chart codes (see ChartOfAccountsSeeder).
     */
    protected function resolveGlAccountId(?int $accountId, string $fallbackAccountCode): int
    {
        if ($accountId !== null) {
            return $accountId;
        }

        return Account::where('account_code', $fallbackAccountCode)->value('id') ?? 1;
    }

    protected function postRepaymentJournal(Loan $loan, string $date, float $principalPaid, float $interestPaid, float $penaltyPaid, ?string $reference = null, ?int $paymentSourceAccountId = null): \App\Models\Transaction
    {
        $product = $loan->product;
        $total = $principalPaid + $interestPaid + $penaltyPaid;

        $lines = [
            [
                'account_id'  => $paymentSourceAccountId ?? $this->resolveGlAccountId($product->disbursement_account_id, '1001'),
                'debit'       => $total,
                'credit'      => 0,
                'description' => "Loan repayment - {$loan->loan_number}",
            ],
        ];

        if ($principalPaid > 0) {
            $lines[] = [
                'account_id'  => $this->resolveGlAccountId($product->receivable_account_id, '1101'),
                'debit'       => 0,
                'credit'      => $principalPaid,
                'description' => "Principal repayment - {$loan->loan_number}",
            ];
        }

        if ($interestPaid > 0) {
            $lines[] = [
                'account_id'  => $this->resolveGlAccountId($product->interest_income_account_id, '4001'),
                'debit'       => 0,
                'credit'      => $interestPaid,
                'description' => "Interest income - {$loan->loan_number}",
            ];
        }

        if ($penaltyPaid > 0) {
            $lines[] = [
                'account_id'  => $this->resolveGlAccountId($product->penalty_income_account_id, '4004'),
                'debit'       => 0,
                'credit'      => $penaltyPaid,
                'description' => "Penalty income - {$loan->loan_number}",
            ];
        }

        return $this->accounting->post(
            $date,
            "Loan repayment: {$loan->loan_number}",
            $lines,
            'loan',
            $loan->id,
            $reference ?: null
        );
    }

    protected function generateLoanNumber(): string
    {
        $prefix = 'LN';
        $year   = now()->format('Y');

        // Query raw table (including soft-deleted) so we never reuse a number
        $last = DB::table('loans')
                  ->where('loan_number', 'like', "{$prefix}-{$year}-%")
                  ->max(DB::raw("CAST(SUBSTRING_INDEX(loan_number, '-', -1) AS UNSIGNED)")) ?? 0;

        return "{$prefix}-{$year}-" . str_pad((int) $last + 1, 5, '0', STR_PAD_LEFT);
    }
}
