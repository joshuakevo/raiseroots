<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
            'view clients', 'create clients', 'edit clients', 'delete clients',

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

            // Member Shares
            'manage shares',

            // Administration
            'manage branches', 'manage users', 'manage settings', 'manage backup',

            // Groups
            'view groups', 'manage groups',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ── ROLES ──────────────────────────────────────────────

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // Admin — everything except backup management
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(
            Permission::whereNotIn('name', ['manage backup'])->get()
        );

        // Cashier — full operational access; products/payroll management is admin/super_admin only
        $cashier = Role::firstOrCreate(['name' => 'cashier']);
        $cashier->syncPermissions([
            'view dashboard',
            'view clients', 'create clients', 'edit clients', 'delete clients',
            'view accounts', 'view transactions', 'create transactions', 'reverse transactions',
            'view loans', 'create loans', 'disburse loans', 'repay loans',
            'view savings', 'create savings', 'deposit savings', 'withdraw savings', 'transfer savings',
            'view fixed-deposits', 'create fixed-deposits', 'mature fixed-deposits',
            'use teller',
            'view reports',
            'view groups', 'manage groups',
            'manage shares',
            'view employees',
        ]);

        // Staff — view only, no create/edit/delete
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions([
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
        $manager->syncPermissions([
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
        ]);

        Role::firstOrCreate(['name' => 'group_leader']);
        Role::firstOrCreate(['name' => 'group_member']);

        // Client portal — no admin permissions, only their own data via portal routes
        Role::firstOrCreate(['name' => 'client']);
    }
}
