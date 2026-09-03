<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\Client;
use App\Models\MemberShare;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReconcileData extends Command
{
    protected $signature   = 'eltech:reconcile {--dry-run : Show what would change without saving}';
    protected $description = 'Recalculate all denormalized fields from source of truth and fix any drift';

    private bool $dryRun   = false;
    private int  $fixed    = 0;
    private int  $ok       = 0;

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        $this->line('');
        $this->reconcileSavingsBalances();
        $this->reconcileLoanScheduleStatuses();
        $this->reconcileLoanOutstandingBalances();
        $this->reconcileLoanDefaultStatus();
        $this->reconcileMemberShareStatuses();
        $this->reconcileClientMembershipStatuses();

        $this->line('');
        $this->info("Done. Fixed: {$this->fixed}  |  Already correct: {$this->ok}");

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // 1. Savings account balances — source of truth: savings_transactions.balance_after
    // -----------------------------------------------------------------------
    private function reconcileSavingsBalances(): void
    {
        $this->info('Checking savings account balances...');

        SavingsAccount::all()->each(function (SavingsAccount $account) {
            $last = SavingsTransaction::where('savings_account_id', $account->id)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $correct = $last ? (float) $last->balance_after : 0.0;
            $stored  = (float) $account->balance;

            if (abs($correct - $stored) > 0.005) {
                $this->warn("  [SAVINGS] {$account->account_number}: stored={$stored}  correct={$correct}");
                if (!$this->dryRun) {
                    $account->update(['balance' => $correct]);
                }
                $this->fixed++;
            } else {
                $this->ok++;
            }
        });
    }

    // -----------------------------------------------------------------------
    // 2. Loan schedule statuses — source of truth: principal_paid/interest_paid vs due
    // -----------------------------------------------------------------------
    private function reconcileLoanScheduleStatuses(): void
    {
        $this->info('Checking loan schedule statuses...');

        $today = Carbon::today()->toDateString();

        LoanSchedule::all()->each(function (LoanSchedule $s) use ($today) {
            $fullyPaid = ($s->principal_paid >= $s->principal_due) && ($s->interest_paid >= $s->interest_due);
            $partial   = !$fullyPaid && ($s->principal_paid > 0 || $s->interest_paid > 0);

            if ($fullyPaid) {
                $correct = 'paid';
            } elseif ($partial) {
                $correct = $s->due_date <= $today ? 'overdue' : 'partial';
            } else {
                $correct = $s->due_date < $today ? 'overdue' : 'pending';
            }

            if ($s->status !== $correct) {
                $this->warn("  [SCHEDULE] Loan #{$s->loan_id} Install #{$s->installment_no}: status={$s->status}  correct={$correct}");
                if (!$this->dryRun) {
                    $s->update(['status' => $correct]);
                }
                $this->fixed++;
            } else {
                $this->ok++;
            }
        });
    }

    // -----------------------------------------------------------------------
    // 3. Loan outstanding balances — source of truth: repayments + schedules
    // -----------------------------------------------------------------------
    private function reconcileLoanOutstandingBalances(): void
    {
        $this->info('Checking loan outstanding balances...');

        Loan::whereIn('status', ['active', 'defaulted'])->each(function (Loan $loan) {
            // Principal = original principal minus all principal paid via repayments
            $correctPrincipal = max(0, $loan->principal - $loan->repayments()->sum('principal_paid'));

            // Interest = sum of (interest_due - interest_paid) across unpaid/partial schedules
            $correctInterest = max(0, (float) DB::table('loan_schedules')
                ->where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->selectRaw('COALESCE(SUM(interest_due - interest_paid), 0) as rem')
                ->value('rem'));

            $updates = [];

            if (abs($correctPrincipal - (float) $loan->outstanding_principal) > 0.005) {
                $this->warn("  [LOAN] #{$loan->loan_number} principal: stored={$loan->outstanding_principal}  correct={$correctPrincipal}");
                $updates['outstanding_principal'] = $correctPrincipal;
                $this->fixed++;
            } else {
                $this->ok++;
            }

            if (abs($correctInterest - (float) $loan->outstanding_interest) > 0.005) {
                $this->warn("  [LOAN] #{$loan->loan_number} interest: stored={$loan->outstanding_interest}  correct={$correctInterest}");
                $updates['outstanding_interest'] = $correctInterest;
                $this->fixed++;
            } else {
                $this->ok++;
            }

            // Auto-close if nothing outstanding
            if ($correctPrincipal <= 0 && $correctInterest <= 0 && (float) $loan->outstanding_penalty <= 0) {
                if ($loan->status !== 'closed') {
                    $this->warn("  [LOAN] #{$loan->loan_number} should be closed (zero outstanding)");
                    $updates['status'] = 'closed';
                    $this->fixed++;
                }
            }

            if ($updates && !$this->dryRun) {
                $loan->update($updates);
            }
        });
    }

    // -----------------------------------------------------------------------
    // 3b. Loan default status — source of truth: any unpaid installment 1+ day overdue
    // -----------------------------------------------------------------------
    private function reconcileLoanDefaultStatus(): void
    {
        $this->info('Checking loan default status...');

        $today = Carbon::today()->toDateString();

        Loan::whereIn('status', ['active', 'defaulted'])->each(function (Loan $loan) use ($today) {
            $isOverdue = $loan->schedules()
                ->where('due_date', '<', $today)
                ->where('status', '!=', 'paid')
                ->exists();

            $correct = $isOverdue ? 'defaulted' : 'active';

            if ($loan->status !== $correct) {
                $this->warn("  [LOAN] #{$loan->loan_number} status: stored={$loan->status}  correct={$correct}");
                if (!$this->dryRun) {
                    $loan->update(['status' => $correct]);
                }
                $this->fixed++;
            } else {
                $this->ok++;
            }
        });
    }

    // -----------------------------------------------------------------------
    // 4. Member share statuses — source of truth: amount_paid vs share_value
    // -----------------------------------------------------------------------
    private function reconcileMemberShareStatuses(): void
    {
        $this->info('Checking member share statuses...');

        MemberShare::all()->each(function (MemberShare $share) {
            $paid  = (float) $share->amount_paid;
            $total = (float) $share->share_value;

            $correct = match(true) {
                $paid <= 0        => 'unpaid',
                $paid >= $total   => 'paid',
                default           => 'partial',
            };

            if ($share->status !== $correct) {
                $this->warn("  [SHARE] #{$share->share_number}: status={$share->status}  correct={$correct}");
                if (!$this->dryRun) {
                    $share->update(['status' => $correct]);
                }
                $this->fixed++;
            } else {
                $this->ok++;
            }
        });
    }

    // -----------------------------------------------------------------------
    // 5. Client membership fee status — source of truth: membership_fee_paid vs membership_fee
    // -----------------------------------------------------------------------
    private function reconcileClientMembershipStatuses(): void
    {
        $this->info('Checking client membership fee statuses...');

        Client::all()->each(function (Client $client) {
            $paid  = (float) $client->membership_fee_paid;
            $total = (float) $client->membership_fee;

            $correct = match(true) {
                $paid <= 0      => 'unpaid',
                $paid >= $total => 'paid',
                default         => 'partial',
            };

            if ($client->membership_fee_status !== $correct) {
                $this->warn("  [CLIENT] {$client->client_number} {$client->name}: status={$client->membership_fee_status}  correct={$correct}");
                if (!$this->dryRun) {
                    $client->update(['membership_fee_status' => $correct]);
                }
                $this->fixed++;
            } else {
                $this->ok++;
            }
        });
    }
}
