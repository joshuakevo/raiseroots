<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\TransactionLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time corrective fix: a run of loan disbursements posted their receivable leg to
 * the wrong GL account — Current Assets (1000), Cash On Hand (1001), or Loans
 * Receivable — Emergency (1103) — instead of Loan Receivables (1100), because the
 * (single, shared) loan product's `receivable_account_id` was repeatedly reconfigured
 * between disbursements on 2026-08-20/21 before settling on 1100. Disbursements already
 * posted to 1100 are left untouched.
 *
 * Corrects each wrong line's `account_id` directly (no reversal, no re-disbursement) —
 * the loan side (principal, schedule, dates, status) was never wrong, only the GL
 * account on that one debit line. Loan-module reversal logic keys off
 * `transactions.module_id`, not `transaction_lines.account_id`, and the manual-journal
 * sub-ledger sync only runs for module='manual' transactions — so this direct edit is
 * invisible to both and won't be re-picked-up or double-applied by anything else in the
 * app; General Ledger, the account ledger view, Trial Balance and the Balance Sheet all
 * compute balances live off `transaction_lines`, so the correction is reflected
 * immediately everywhere with nothing left stale.
 *
 * Remove this command (and its Settings buttons) once the fix has been confirmed
 * complete on production.
 */
class FixLoanReceivablePostings extends Command
{
    protected $signature = 'eltech:fix-loan-receivables {--commit : Actually apply the fix; without this flag, only previews what would change}';

    protected $description = 'One-time fix for loan disbursements mis-posted to the wrong GL account instead of Loan Receivables';

    /** Account codes considered wrong destinations for the receivable leg. */
    private const WRONG_CODES = ['1000', '1001', '1103'];

    private const CORRECT_ACCOUNT_CODE = '1100';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        if (! $commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $correctAccount = Account::where('account_code', self::CORRECT_ACCOUNT_CODE)->first();
        if (! $correctAccount) {
            $this->error('Account ' . self::CORRECT_ACCOUNT_CODE . ' (Loan Receivables) not found.');
            return self::FAILURE;
        }

        $wrongAccountIds = Account::whereIn('account_code', self::WRONG_CODES)->pluck('id', 'account_code');
        if ($wrongAccountIds->count() !== count(self::WRONG_CODES)) {
            $this->error('One or more of the wrong-destination accounts (' . implode(', ', self::WRONG_CODES) . ') not found.');
            return self::FAILURE;
        }

        $lines = TransactionLine::whereIn('account_id', $wrongAccountIds->values())
            ->where('description', 'like', 'Loan receivable - %')
            ->whereHas('transaction', function ($q) {
                $q->where('module', 'loan')
                    ->where('description', 'like', 'Loan disbursement:%');
            })
            ->with('transaction', 'account')
            ->get()
            ->sortBy('transaction_id');

        if ($lines->isEmpty()) {
            $this->info('No mis-posted loan disbursement lines found. Nothing to do.');
            return self::SUCCESS;
        }

        $matches = [];

        foreach ($lines as $line) {
            $transaction = $line->transaction;
            $loan = Loan::find($transaction->module_id);

            $matches[] = [
                'line'          => $line,
                'wrong_account' => $line->account,
                'amount'        => (float) $line->debit,
                'transaction'   => $transaction,
                'loan'          => $loan,
                'has_repayments' => $loan?->repayments()->exists() ?? false,
            ];
        }

        $this->line('');
        $this->table(
            ['Wrong account', 'Amount', 'Transaction', 'Loan #', 'Client', 'Repayments?'],
            collect($matches)->map(fn ($m) => [
                "{$m['wrong_account']->account_code} — {$m['wrong_account']->account_name}",
                number_format($m['amount'], 2),
                $m['transaction']->reference,
                $m['loan']?->loan_number ?? "(loan #{$m['transaction']->module_id} not found)",
                $m['loan'] ? optional($m['loan']->client)->name : '—',
                $m['has_repayments'] ? 'YES — review manually' : 'no',
            ])
        );

        $totalAmount = collect($matches)->sum('amount');
        $this->info(count($matches) . ' line(s) found, totaling ' . number_format($totalAmount, 2) . '.');

        $withRepayments = collect($matches)->filter(fn ($m) => $m['has_repayments']);
        if ($withRepayments->isNotEmpty()) {
            $this->warn($withRepayments->count() . ' of these loans already have repayments — their repayment journal(s) may also reference the wrong account and are not touched by this command. Review those manually.');
        }

        if (! $commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        $fixed = 0;

        DB::transaction(function () use ($matches, $correctAccount, &$fixed) {
            foreach ($matches as $m) {
                $m['line']->update(['account_id' => $correctAccount->id]);

                // Keep the product's receivable account aligned so future disbursements don't repeat this.
                $product = $m['loan']?->product;
                if ($product && $product->receivable_account_id !== $correctAccount->id) {
                    $product->update(['receivable_account_id' => $correctAccount->id]);
                }

                $fixed++;
            }

            AuditLog::create([
                'user_id'     => auth()->id(),
                'event'       => 'update',
                'module'      => 'Chart of Accounts',
                'description' => "Corrected {$fixed} loan-disbursement receivable line(s) from mis-posted accounts to " . self::CORRECT_ACCOUNT_CODE . ' via eltech:fix-loan-receivables. Transactions: '
                    . collect($matches)->map(fn ($m) => $m['transaction']->reference)->implode(', '),
            ]);
        });

        $this->line('');
        $this->info("Done. Corrected {$fixed} line(s).");

        return self::SUCCESS;
    }
}
