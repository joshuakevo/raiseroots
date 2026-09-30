<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Loan;
use App\Models\TransactionLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (but safe to re-run) fix: Admin Fee is now part of the loan balance -
 * collected through ordinary repayments (priority penalty -> interest -> admin fee ->
 * principal) - rather than tracked separately. Every loan that already has `admin_cost`
 * set (from the disburse screen or the earlier CSV backfill) needs its new
 * `outstanding_admin_fee` initialized to match, so it's correctly treated as part of
 * what's still owed the next time a repayment is processed.
 *
 * outstanding_admin_fee = admin_cost - (whatever's already been credited to GL 4009 for
 * that loan) - so a loan whose Admin Fee was already collected (e.g. LN-2026-00177,
 * "Admin cost - LN-2026-00177" posted manually before this feature existed) correctly
 * starts at 0, not admin_cost. Idempotent: always recomputed from the GL, so re-running
 * this after real repayments have already chipped away at outstanding_admin_fee doesn't
 * undo that progress - the GL sum already reflects it, from any source (Record Admin
 * Fee's "Admin cost - X" or an ordinary repayment's "Admin fee - X" both credit the same
 * account and are counted).
 *
 * Deliberately skips closed loans - one that's already fully settled under the old model
 * is left alone rather than reopened with a newly-discovered admin fee balance, which
 * would be a much bigger, separate decision.
 *
 * Remove this command (and its Settings buttons) once the fix has been confirmed
 * complete on production.
 */
class BackfillOutstandingAdminFee extends Command
{
    protected $signature = 'eltech:backfill-outstanding-admin-fee {--commit : Actually apply the fix; without this flag, only previews what would change}';

    protected $description = 'One-time fix: initialize loans.outstanding_admin_fee from admin_cost minus whatever has already been collected via the GL';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $feeAccountId = Account::where('account_code', '4009')->value('id');
        if (!$feeAccountId) {
            $this->error('GL account 4009 (Loan Administrative Fee) not found.');
            return self::FAILURE;
        }

        $loans = Loan::where('admin_cost', '>', 0)
            ->where('status', '!=', 'closed')
            ->get(['id', 'loan_number', 'admin_cost', 'outstanding_admin_fee', 'status']);

        if ($loans->isEmpty()) {
            $this->info('No loans with admin_cost set (outside closed ones) — nothing to do.');
            return self::SUCCESS;
        }

        $collected = TransactionLine::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transaction_lines.account_id', $feeAccountId)
            ->where('transactions.module', 'loan')
            ->whereIn('transactions.module_id', $loans->pluck('id'))
            ->groupBy('transactions.module_id')
            ->selectRaw('transactions.module_id as loan_id, SUM(transaction_lines.credit) as amount')
            ->pluck('amount', 'loan_id');

        $changes = [];
        foreach ($loans as $loan) {
            $newValue = round(max(0, $loan->admin_cost - (float) ($collected[$loan->id] ?? 0)), 2);
            if (abs($newValue - (float) $loan->outstanding_admin_fee) > 0.01) {
                $changes[] = ['loan' => $loan, 'from' => (float) $loan->outstanding_admin_fee, 'to' => $newValue];
            }
        }

        if (!$commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        if (empty($changes)) {
            $this->info('All ' . $loans->count() . ' eligible loan(s) already correct — nothing to change.');
            return self::SUCCESS;
        }

        $this->line(($commit ? 'Setting' : 'Will set') . ' outstanding_admin_fee on ' . count($changes) . ' loan(s):');
        $this->table(['Loan #', 'Status', 'From', 'To'], array_map(
            fn ($c) => [$c['loan']->loan_number, $c['loan']->status, number_format($c['from'], 2), number_format($c['to'], 2)],
            $changes
        ));

        if (!$commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                $c['loan']->update(['outstanding_admin_fee' => $c['to']]);
            }
        });

        $this->line('');
        $this->info('Done. ' . count($changes) . ' loan(s) updated.');

        return self::SUCCESS;
    }
}
