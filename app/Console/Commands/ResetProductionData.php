<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Go-live wipe" — clears out all transactional/operational data while leaving the
 * setup that took real effort to configure intact: chart of accounts, product
 * catalogs (loan/savings/FD), branches, loan collateral categories, financial
 * periods, system settings, and the roles/permissions structure. Exactly one user
 * survives (the operator running it, or --keep-user), guaranteed to hold
 * super_admin afterwards so there's always a way back in.
 *
 * Irreversible — this host has no shell/SSH access, so there's no separate backup
 * step available from here. Preview (no --commit) before ever running --commit.
 */
class ResetProductionData extends Command
{
    protected $signature = 'eltech:reset-production-data
        {--commit : Actually perform the wipe; without this flag, only previews what would be deleted}
        {--keep-user= : ID of the user to keep as the sole super_admin; defaults to the currently authenticated user}';

    protected $description = 'Wipe all client/transaction data, keeping chart of accounts, products, branches, and one superadmin';

    /** Tables cleared entirely — all client/transaction/operational data. */
    private const WIPE_TABLES = [
        'Clients'                  => 'clients',
        'Journal transactions'     => 'transactions',
        'Journal transaction lines' => 'transaction_lines',
        'Loans'                    => 'loans',
        'Loan schedules'           => 'loan_schedules',
        'Loan repayments'          => 'loan_repayments',
        'Loan guarantors'          => 'loan_guarantors',
        'Loan collaterals'         => 'loan_collaterals',
        'Savings accounts'         => 'savings_accounts',
        'Savings transactions'     => 'savings_transactions',
        'Fixed deposits'           => 'fixed_deposits',
        'Member shares'            => 'member_shares',
        'Share transactions'       => 'share_transactions',
        'Employees'                => 'employees',
        'Payroll runs'             => 'payroll_runs',
        'Payroll items'            => 'payroll_items',
        'Audit logs'               => 'audit_logs',
        'Groups'                   => 'groups',
        'Group members'            => 'group_members',
        'Group transactions'       => 'group_transactions',
        'Client portal users'      => 'client_portal_users',
        'SMS logs'                 => 'sms_logs',
        'SMS subscriptions'        => 'sms_subscriptions',
        'Password reset tokens'    => 'password_resets',
        'Failed jobs'              => 'failed_jobs',
        'API tokens'               => 'personal_access_tokens',
    ];

    /** Left untouched — setup/config, never treated as wipeable "data". */
    private const KEPT_TABLES = [
        'Chart of accounts'         => 'accounts',
        'Loan products'             => 'loan_products',
        'Savings products'          => 'savings_products',
        'Fixed deposit products'    => 'fixed_deposit_products',
        'Branches'                  => 'branches',
        'Loan collateral categories' => 'loan_collateral_categories',
        'Financial periods'         => 'financial_periods',
        'System settings'           => 'system_settings',
    ];

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $keepUserId = $this->option('keep-user') ?: auth()->id();

        if (! $keepUserId) {
            $this->error('No user to keep — pass --keep-user=ID (no authenticated session in this context).');
            return self::FAILURE;
        }

        $keepUser = User::find($keepUserId);
        if (! $keepUser) {
            $this->error("User #{$keepUserId} not found.");
            return self::FAILURE;
        }

        if (! $commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        $this->line('Will be WIPED:');
        $rows = [];
        foreach (self::WIPE_TABLES as $label => $table) {
            $count = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            $rows[] = [$label, $count];
        }
        $usersToDelete = User::where('id', '!=', $keepUser->id)->count();
        $rows[] = ['Users (all except the one kept)', $usersToDelete];
        $this->table(['Table', 'Rows'], $rows);

        $this->line('');
        $this->line('Will be KEPT untouched:');
        $keptRows = [];
        foreach (self::KEPT_TABLES as $label => $table) {
            $count = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            $keptRows[] = [$label, $count];
        }
        $this->table(['Table', 'Rows'], $keptRows);

        $this->line('');
        $this->info("User kept as super_admin: #{$keepUser->id} — {$keepUser->name} ({$keepUser->email})");

        if (! $commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::WIPE_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }

            User::where('id', '!=', $keepUser->id)->delete();

            if (! $keepUser->hasRole('super_admin')) {
                $keepUser->assignRole('super_admin');
            }
            if (! $keepUser->is_active) {
                $keepUser->update(['is_active' => true]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // audit_logs was just truncated, so this is the first row in the fresh log.
        AuditLog::create([
            'user_id'     => $keepUser->id,
            'event'       => 'delete',
            'module'      => 'Settings',
            'description' => "Production data reset via eltech:reset-production-data. Wiped {$usersToDelete} other user(s) and all client/transaction data. Kept: chart of accounts, products, branches, collateral categories, financial periods, system settings, and this superadmin account.",
        ]);

        $this->line('');
        $this->info('Done. Production data reset complete.');

        return self::SUCCESS;
    }
}
