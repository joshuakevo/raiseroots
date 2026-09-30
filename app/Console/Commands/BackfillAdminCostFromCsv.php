<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Loan;
use Illuminate\Console\Command;

/**
 * One-time fix: the 272 loans from the August/September disbursement import predate the
 * `loans.admin_cost` column, so they show as "Not paid" on the Loans list with no
 * expected amount - even though the source sheet (docs/disbursements.csv) always had
 * one. This reads that sheet and fills in `admin_cost` for exactly the loans that are
 * still at its default of 0, using client_id + issue_date + loan_amount to match each
 * row to the one loan it created. Only touches admin_cost - no GL entry, no other
 * column - so it's purely filling in a reference figure, not re-collecting anything.
 *
 * Reads the same file already uploaded for the loan import -
 * storage/app/imports/loan-disbursements.csv - no separate upload needed. This command
 * only ever reads that file; it never touches the "Import Loan Disbursements" button/
 * route, so there's no risk of it re-triggering that (unsafe-to-rerun) import. If a
 * different file - wrong columns - ends up at that path, the header check below fails
 * loudly rather than silently matching nothing or the wrong rows.
 *
 * Remove this command (and its Settings buttons) once the fix has been confirmed
 * complete on production.
 */
class BackfillAdminCostFromCsv extends Command
{
    protected $signature = 'eltech:backfill-admin-cost {--commit : Actually apply the fix; without this flag, only previews what would change}';

    protected $description = 'One-time fix: fill in loans.admin_cost from docs/disbursements.csv for loans that predate that column';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $path = storage_path('app/imports/loan-disbursements.csv');

        if (!file_exists($path)) {
            $this->error('No file found at storage/app/imports/loan-disbursements.csv — upload it there first (via File Manager), then run this again.');
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);
        $col = array_flip($header);

        foreach (['client_id', 'issue_date', 'loan_amount', 'admin_cost'] as $required) {
            if (!isset($col[$required])) {
                $this->error("Column \"{$required}\" not found in the file's header.");
                fclose($handle);
                return self::FAILURE;
            }
        }

        $matched = [];
        $skipped = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (!array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }

            $clientNumber = trim((string) $row[$col['client_id']]);
            $issueDate    = trim((string) $row[$col['issue_date']]);
            $loanAmount   = (float) $row[$col['loan_amount']];
            $adminCost    = (float) $row[$col['admin_cost']];

            if ($clientNumber === '' || $adminCost <= 0.01) {
                continue;
            }

            $client = Client::where('client_number', $clientNumber)->first();
            if (!$client) {
                $skipped[] = "Row {$rowNum}: {$clientNumber} — no client with that number";
                continue;
            }

            $candidates = Loan::where('client_id', $client->id)
                ->where('disbursement_date', $issueDate)
                ->where('principal', $loanAmount)
                ->where('admin_cost', 0)
                ->get();

            if ($candidates->count() !== 1) {
                $skipped[] = "Row {$rowNum}: {$clientNumber} on {$issueDate} for " . number_format($loanAmount, 2)
                    . ' — ' . ($candidates->count() === 0 ? 'no matching loan (already backfilled, or none disbursed with that amount/date)' : "{$candidates->count()} matching loans, ambiguous");
                continue;
            }

            $matched[] = ['loan' => $candidates->first(), 'admin_cost' => $adminCost, 'client_number' => $clientNumber];
        }
        fclose($handle);

        if (!$commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        $this->line(($commit ? 'Setting' : 'Will set') . " admin_cost on {$this->pluralCount($matched)}:");
        $this->table(['Loan #', 'Client', 'Admin Cost'], array_map(
            fn ($m) => [$m['loan']->loan_number, $m['client_number'], number_format($m['admin_cost'], 2)],
            $matched
        ));

        if (!empty($skipped)) {
            $this->line('');
            $this->warn('Skipped (' . count($skipped) . '):');
            foreach ($skipped as $line) {
                $this->line("  {$line}");
            }
        }

        if (!$commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        foreach ($matched as $m) {
            $m['loan']->update(['admin_cost' => $m['admin_cost']]);
        }

        $this->line('');
        $this->info('Done. ' . count($matched) . ' loan(s) updated.');

        return self::SUCCESS;
    }

    private function pluralCount(array $rows): string
    {
        return count($rows) . ' loan' . (count($rows) === 1 ? '' : 's');
    }
}
