<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Baseline roles and permissions. Grants only - never revokes.
 *
 * Administration > Roles lets an admin customize any role's permissions after the fact
 * (add extras, or take some away). This seeder re-runs every time a new permission is
 * introduced (e.g. after a deploy), and must never undo those customizations - so every
 * role here uses givePermissionTo() (adds what's missing, leaves everything else alone),
 * never syncPermissions() (replaces the whole set, wiping any custom grant or revocation
 * made through the UI back to whatever is hardcoded below).
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'view dashboard',

            // Clients
            'view clients', 'create clients', 'edit clients', 'delete clients', 'assign relationship manager',
            'edit client number',

            // Accounts / Chart of Accounts
            'view accounts', 'create accounts', 'edit accounts',

            // Transactions (journal entries)
            'view transactions', 'create transactions', 'reverse transactions',

            // Loan Products
            'view loan-products', 'create loan-products', 'edit loan-products',

            // Loans
            'view loans', 'create loans', 'approve loans', 'disburse loans', 'repay loans',

            // Savings Products
            'view savings-products', 'create savings-products', 'edit savings-products',

            // Savings Accounts
            'view savings', 'create savings', 'deposit savings', 'withdraw savings', 'transfer savings',

            // FD Products
            'view fd-products', 'create fd-products', 'edit fd-products',

            // Fixed Deposits
            'view fixed-deposits', 'create fixed-deposits', 'mature fixed-deposits',

            // Teller
            'use teller',

            // Reports
            'view reports',

            // Employees
            'view employees', 'create employees', 'edit employees',

            // Payroll
            'view payroll', 'create payroll', 'process payroll', 'delete payroll',

            // Staff Analysis (Loan Officer performance)
            'view staff analysis',

            // Member Shares
            'manage shares',

            // Administration
            'manage branches', 'manage users', 'manage settings', 'manage backup',

            // Groups
            'view groups', 'manage groups',

            // SMS Messaging
            'send sms',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── ROLES ──────────────────────────────────────────────

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin — everything except backup management
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(
            Permission::whereNotIn('name', ['manage backup'])->get()
        );

        // Cashier — full operational access; products/payroll management is admin/super_admin only
        $cashier = Role::firstOrCreate(['name' => 'cashier']);
        $cashier->givePermissionTo([
            'view dashboard',
            'view clients', 'create clients', 'edit clients', 'delete clients', 'assign relationship manager',
            'view accounts', 'view transactions', 'create transactions', 'reverse transactions',
            'view loans', 'create loans', 'disburse loans', 'repay loans',
            'view savings', 'create savings', 'deposit savings', 'withdraw savings', 'transfer savings',
            'view fixed-deposits', 'create fixed-deposits', 'mature fixed-deposits',
            'use teller',
            'view reports',
            'view groups', 'manage groups',
            'manage shares',
            'view employees',
            'send sms',
        ]);

        // Staff — view only, no create/edit/delete
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->givePermissionTo([
            'view dashboard',
            'view clients',
            'view accounts',
            'view loan-products',
            'view loans',
            'view savings-products',
            'view savings',
            'view fd-products',
            'view fixed-deposits',
            'view reports',
            'view groups',
            'view employees',
        ]);

        // Manager — reviews and approves loans before they can be disbursed,
        // plus enough view access to make an informed approval decision
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->givePermissionTo([
            'view dashboard',
            'view clients',
            'view accounts',
            'view loan-products',
            'view loans', 'approve loans',
            'view savings-products',
            'view savings',
            'view fd-products',
            'view fixed-deposits',
            'view reports',
            'view groups',
            'view employees',
            'view staff analysis',
            'edit client number',
        ]);

        Role::firstOrCreate(['name' => 'group_leader']);
        Role::firstOrCreate(['name' => 'group_member']);

        // Client portal — no admin permissions, only their own data via portal routes
        Role::firstOrCreate(['name' => 'client']);
    }
}
