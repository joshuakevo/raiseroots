<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ─── ASSETS ──────────────────────────────────────────────────────
            ['code' => '1000', 'name' => 'Current Assets',              'type' => 'asset',     'parent' => null],
            ['code' => '1001', 'name' => 'Cash on Hand',                'type' => 'asset',     'parent' => '1000'],
            ['code' => '1002', 'name' => 'Cash at Bank',                'type' => 'asset',     'parent' => '1000'],
            ['code' => '1003', 'name' => 'Mobile Money Wallet',         'type' => 'asset',     'parent' => '1000'],
            ['code' => '1100', 'name' => 'Loan Receivables',            'type' => 'asset',     'parent' => null],
            ['code' => '1101', 'name' => 'Loans Receivable — General',  'type' => 'asset',     'parent' => '1100'],
            ['code' => '1102', 'name' => 'Loans Receivable — Business', 'type' => 'asset',     'parent' => '1100'],
            ['code' => '1103', 'name' => 'Loans Receivable — Emergency','type' => 'asset',     'parent' => '1100'],
            ['code' => '1199', 'name' => 'Less: Unearned Interest & Fees', 'type' => 'asset', 'parent' => '1100'],
            ['code' => '1200', 'name' => 'Fixed Assets',                'type' => 'asset',     'parent' => null],
            ['code' => '1201', 'name' => 'Office Equipment',            'type' => 'asset',     'parent' => '1200'],
            ['code' => '1202', 'name' => 'Computers & IT Equipment',    'type' => 'asset',     'parent' => '1200'],
            ['code' => '1203', 'name' => 'Furniture & Fittings',        'type' => 'asset',     'parent' => '1200'],
            ['code' => '1300', 'name' => 'Other Assets',                'type' => 'asset',     'parent' => null],
            ['code' => '1301', 'name' => 'Prepaid Expenses',            'type' => 'asset',     'parent' => '1300'],
            ['code' => '1302', 'name' => 'Accrued Income',              'type' => 'asset',     'parent' => '1300'],

            // ─── LIABILITIES ─────────────────────────────────────────────────
            ['code' => '2000', 'name' => 'Current Liabilities',         'type' => 'liability', 'parent' => null],
            ['code' => '2001', 'name' => 'Member Savings (General)',     'type' => 'liability', 'parent' => '2000'],
            ['code' => '2002', 'name' => 'Fixed Deposit Liabilities',   'type' => 'liability', 'parent' => '2000'],
            ['code' => '2003', 'name' => 'Interest Payable (FD)',       'type' => 'liability', 'parent' => '2000'],
            ['code' => '2004', 'name' => 'Accrued Expenses',            'type' => 'liability', 'parent' => '2000'],
            ['code' => '2005', 'name' => 'Group Member Savings',        'type' => 'liability', 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Long-Term Liabilities',       'type' => 'liability', 'parent' => null],
            ['code' => '2101', 'name' => 'Borrowings',                  'type' => 'liability', 'parent' => '2100'],

            // ─── EQUITY ──────────────────────────────────────────────────────
            ['code' => '3000', 'name' => 'Equity',                      'type' => 'equity',    'parent' => null],
            ['code' => '3001', 'name' => 'Share Capital',               'type' => 'equity',    'parent' => '3000'],
            ['code' => '3002', 'name' => 'Retained Earnings',           'type' => 'equity',    'parent' => '3000'],
            ['code' => '3003', 'name' => 'Statutory Reserve Fund',      'type' => 'equity',    'parent' => '3000'],

            // ─── REVENUE ─────────────────────────────────────────────────────
            ['code' => '4000', 'name' => 'Revenue',                     'type' => 'revenue',   'parent' => null],
            ['code' => '4001', 'name' => 'Interest Income — General Loans','type' => 'revenue','parent' => '4000'],
            ['code' => '4002', 'name' => 'Interest Income — Business Loans','type' => 'revenue','parent' => '4000'],
            ['code' => '4003', 'name' => 'Interest Income — Emergency Loans','type' => 'revenue','parent' => '4000'],
            ['code' => '4004', 'name' => 'Penalty Income',              'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4005', 'name' => 'Processing Fees',             'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4006', 'name' => 'Withdrawal Fee Income',       'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4007', 'name' => 'Other Income',                'type' => 'revenue',   'parent' => '4000'],

            // ─── EXPENSES ────────────────────────────────────────────────────
            ['code' => '5000', 'name' => 'Expenses',                    'type' => 'expense',   'parent' => null],
            ['code' => '5001', 'name' => 'Interest Expense — Savings',  'type' => 'expense',   'parent' => '5000'],
            ['code' => '5002', 'name' => 'Interest Expense — Fixed Deposits','type' => 'expense','parent' => '5000'],
            ['code' => '5010', 'name' => 'Interest Expense — Groups',    'type' => 'expense',   'parent' => '5000'],
            ['code' => '5003', 'name' => 'Staff Salaries',              'type' => 'expense',   'parent' => '5000'],
            ['code' => '5004', 'name' => 'Rent & Utilities',            'type' => 'expense',   'parent' => '5000'],
            ['code' => '5005', 'name' => 'Office Supplies',             'type' => 'expense',   'parent' => '5000'],
            ['code' => '5006', 'name' => 'Bad Debt Provision',          'type' => 'expense',   'parent' => '5000'],
            ['code' => '5007', 'name' => 'Depreciation',                'type' => 'expense',   'parent' => '5000'],
            ['code' => '5008', 'name' => 'Bank Charges',                'type' => 'expense',   'parent' => '5000'],
            ['code' => '5009', 'name' => 'Miscellaneous Expenses',      'type' => 'expense',   'parent' => '5000'],
        ];

        $created = [];

        // First pass: create accounts without parents
        foreach ($accounts as $acc) {
            if ($acc['parent'] === null) {
                $model = Account::updateOrCreate(
                    ['account_code' => $acc['code']],
                    ['account_name' => $acc['name'], 'account_type' => $acc['type'], 'parent_id' => null, 'is_active' => true]
                );
                $created[$acc['code']] = $model->id;
            }
        }

        // Second pass: create child accounts
        foreach ($accounts as $acc) {
            if ($acc['parent'] !== null) {
                $parentId = $created[$acc['parent']] ?? null;
                $model = Account::updateOrCreate(
                    ['account_code' => $acc['code']],
                    ['account_name' => $acc['name'], 'account_type' => $acc['type'], 'parent_id' => $parentId, 'is_active' => true]
                );
                $created[$acc['code']] = $model->id;
            }
        }

        $this->command->info('Chart of Accounts seeded: ' . count($accounts) . ' accounts.');
    }
}
