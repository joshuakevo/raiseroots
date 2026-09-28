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
 * periods, system settings, and - deliberately - every user account, role and
 * permission exactly as they are. Staff logins (loan officers, cashiers, etc.)
 * are needed intact immediately after a wipe, e.g. to match a re-imported
 * client's Loan Officer column by name, so users are never touched here.
 *
 * The one adjustment made to `users`: any account's `client_id` (a client-portal
 * login's link to their own client record) is nulled if it pointed at a client
 * this run just deleted — that FK would otherwise dangle, since TRUNCATE with
 * FOREIGN_KEY_CHECKS off skips the column's normal nullOnDelete(). The account
 * itself, its role and its permissions are left exactly as they were.
 *
 * Irreversible — this host has no shell/SSH access, so there's no separate backup
 * step available from here. Preview (no --commit) before ever running --commit.
 */
class ResetProductionData extends Command
{
    protected $signature = 'eltech:reset-production-data
        {--commit : Actually perform the wipe; without this flag, only previews what would be deleted}';

    protected $description = 'Wipe all client/transaction data, keeping chart of accounts, products, branches, settings, and every user/role/permission as-is';

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
        $actingUserId = auth()->id();

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
        $this->table(['Table', 'Rows'], $rows);

        $this->line('');
        $this->line('Will be KEPT untouched:');
        $keptRows = [];
        foreach (self::KEPT_TABLES as $label => $table) {
            $count = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            $keptRows[] = [$label, $count];
        }
        $keptRows[] = ['Users, roles & permissions', User::count()];
        $this->table(['Table', 'Rows'], $keptRows);

        if (! $commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        $danglingPortalLogins = User::whereNotNull('client_id')->count();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (self::WIPE_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }

            // Every client just got deleted, so any account's link to one would dangle -
            // TRUNCATE with FK checks off skips the column's normal nullOnDelete(). The
            // account, its role and its permissions are otherwise left exactly as they were.
            User::whereNotNull('client_id')->update(['client_id' => null]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // audit_logs was just truncated, so this is the first row in the fresh log.
        AuditLog::create([
            'user_id'     => $actingUserId,
            'event'       => 'delete',
            'module'      => 'Settings',
            'description' => "Production data reset via eltech:reset-production-data. Wiped all client/transaction data. Kept: chart of accounts, products, branches, collateral categories, financial periods, system settings, and every user/role/permission unchanged"
                . ($danglingPortalLogins > 0 ? " (cleared the now-dangling client link on {$danglingPortalLogins} account(s))" : '') . '.',
        ]);

        $this->line('');
        $this->info('Done. Production data reset complete. Users, roles and permissions were left untouched.');

        return self::SUCCESS;
    }
}
