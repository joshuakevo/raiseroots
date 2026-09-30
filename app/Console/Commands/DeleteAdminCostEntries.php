<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time corrective fix: the August/September loan disbursement import
 * (LoanImportService, from docs/disbursements.csv) posted each loan's Admin Cost as
 * already collected in cash on the disbursement date. Per instruction, these were not
 * actually collected yet - they should sit as not-yet-recorded until each one is
 * genuinely paid, so the "already collected" postings need to come off the books
 * entirely, not be corrected to a different amount/date.
 *
 * Targets every transaction tagged module='loan' whose description matches
 * "Admin cost - <loan number>" - the exact pattern LoanImportService posts and nothing
 * else in the app creates. Deleting is safe here: TransactionController's reversal
 * dispatcher (reverseLoanImpact) only does something special when a loan-module
 * transaction's description contains "loan disbursement" or "loan repayment" - an
 * "Admin cost" line matches neither, so removing it touches nothing else (the loan's
 * own status/schedule/outstanding balances, which live on the *disbursement*
 * transaction, are untouched). Processing Fee entries (a separate, correctly-collected
 * fee) are left alone.
 *
 * Remove this command (and its Settings buttons) once the fix has been confirmed
 * complete on production.
 */
class DeleteAdminCostEntries extends Command
{
    protected $signature = 'eltech:delete-admin-cost-entries {--commit : Actually delete; without this flag, only previews what would be removed}';

    protected $description = 'One-time fix: remove the "Admin cost" journal entries posted by the loan disbursement import - they were not actually collected';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $transactions = Transaction::where('module', 'loan')
            ->where('description', 'like', 'Admin cost - %')
            ->orderBy('date')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No matching "Admin cost" entries found - nothing to do.');
            return self::SUCCESS;
        }

        $total = 0;
        $rows = [];
        foreach ($transactions as $t) {
            $amount = (float) $t->lines()->sum('credit');
            $total += $amount;
            $rows[] = [$t->reference, $t->date->format('Y-m-d'), $t->description, number_format($amount, 2)];
        }

        if (!$commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        $this->line(($commit ? 'Deleting' : 'Will delete') . " {$transactions->count()} \"Admin cost\" entries, totalling " . number_format($total, 2) . ':');
        $this->table(['Reference', 'Date', 'Description', 'Amount'], $rows);

        if (!$commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($transactions) {
            foreach ($transactions as $t) {
                $t->lines()->delete();
                $t->delete();
            }
        });

        AuditLog::create([
            'user_id'     => auth()->id(),
            'event'       => 'delete',
            'module'      => 'Journal Entries',
            'description' => "Removed {$transactions->count()} \"Admin cost\" entries from the August/September loan import (totalling "
                . number_format($total, 2) . ') via eltech:delete-admin-cost-entries — these fees were not actually collected and should not have been posted as received.',
        ]);

        $this->line('');
        $this->info('Done. ' . $transactions->count() . ' entries removed.');

        return self::SUCCESS;
    }
}
