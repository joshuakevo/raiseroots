<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Loan;
use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time catch-up for loans disbursed before interest + admin fee were accrued into
 * Loan Receivables at disbursement. For each active/defaulted loan not yet accrued,
 * posts DR Loan Receivables / CR 1199 Unearned Interest & Fees (contra-asset) for what's still owed
 * (outstanding_interest + outstanding_admin_fee) and flags the loan `income_accrued`,
 * so its later repayments clear the receivable and release 1199 into income.
 *
 * No income is recognised by this - profit figures are unchanged. Each loan gets its
 * own journal, so a single loan can be undone by reversing its entry (which un-flags
 * it again). Only ever touches a loan once; safe to re-run.
 *
 * Remove this command (and its Settings buttons) once confirmed complete on production.
 */
class AccrueLoanReceivables extends Command
{
    protected $signature = 'eltech:accrue-loan-receivables {--commit : Actually apply the fix; without this flag, only previews what would change}';

    protected $description = 'One-time fix: bring outstanding interest + admin fee on existing loans into Loan Receivables (offset by 1199 Unearned Interest & Fees)';

    public function handle(AccountingService $accounting): int
    {
        $commit = (bool) $this->option('commit');

        $unearnedId = Account::where('account_code', '1199')->value('id');
        if (!$unearnedId) {
            $this->error('GL account 1199 (Unearned Interest & Fees) not found - run migrations first.');
            return self::FAILURE;
        }
        $fallbackReceivableId = Account::where('account_code', '1101')->value('id');

        $loans = Loan::with('product')
            ->whereIn('status', ['active', 'defaulted'])
            ->where('income_accrued', false)
            ->orderBy('id')
            ->get();

        if (!$commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        if ($loans->isEmpty()) {
            $this->info('Every active/defaulted loan is already accrued — nothing to do.');
            return self::SUCCESS;
        }

        $rows  = [];
        $total = 0.0;
        foreach ($loans as $loan) {
            $amount = round($loan->outstanding_interest + $loan->outstanding_admin_fee, 2);
            $total += $amount;
            $rows[] = [$loan->loan_number, $loan->status, number_format($loan->outstanding_interest, 2), number_format($loan->outstanding_admin_fee, 2), number_format($amount, 2)];
        }

        $this->line(($commit ? 'Accruing' : 'Will accrue') . ' ' . $loans->count() . ' loan(s), total ' . number_format($total, 2) . ':');
        $this->table(['Loan #', 'Status', 'Interest', 'Admin Fee', 'To Receivables'], $rows);

        if (!$commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        $date = now()->toDateString();
        DB::transaction(function () use ($loans, $accounting, $unearnedId, $fallbackReceivableId, $date) {
            foreach ($loans as $loan) {
                $amount = round($loan->outstanding_interest + $loan->outstanding_admin_fee, 2);
                if ($amount > 0.01) {
                    $accounting->post(
                        $date,
                        "Loan receivable accrual (opening) - {$loan->loan_number}",
                        [
                            ['account_id' => $loan->product?->receivable_account_id ?? $fallbackReceivableId, 'debit' => $amount, 'credit' => 0, 'client_id' => $loan->client_id, 'description' => "Interest & admin fee receivable - {$loan->loan_number}"],
                            ['account_id' => $unearnedId, 'debit' => 0, 'credit' => $amount, 'description' => "Unearned interest & admin fee - {$loan->loan_number}"],
                        ],
                        'loan',
                        $loan->id
                    );
                }
                $loan->update(['income_accrued' => true]);
            }
        });

        $this->line('');
        $this->info('Done. ' . $loans->count() . ' loan(s) accrued.');

        return self::SUCCESS;
    }
}
