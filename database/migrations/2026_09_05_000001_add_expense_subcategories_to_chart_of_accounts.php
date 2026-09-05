<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** New sub-parent accounts under 5000 — Expenses. */
    private array $newParents = [
        ['code' => '5100', 'name' => 'Personnel Costs'],
        ['code' => '5200', 'name' => 'Financial Charges'],
        ['code' => '5300', 'name' => 'Operating Expenses'],
        ['code' => '5400', 'name' => 'Provisions & Non-Cash'],
    ];

    /** Existing expense account code => new parent code. */
    private array $reassignments = [
        '5001' => '5100', // Salary Expense
        '5003' => '5100', // Staff Salaries
        '5002' => '5200', // Interest Expense — Fixed Deposits
        '5010' => '5200', // Interest Expense — Groups
        '5008' => '5200', // Bank Charges
        '5004' => '5300', // Rent & Utilities
        '5005' => '5300', // Office Supplies
        '5009' => '5300', // Miscellaneous Expenses
        '5006' => '5400', // Bad Debt Provision
        '5007' => '5400', // Depreciation
    ];

    public function up(): void
    {
        $expensesParentId = DB::table('accounts')->where('account_code', '5000')->value('id');
        if (! $expensesParentId) {
            return; // Chart of accounts not seeded yet — nothing to reorganize.
        }

        foreach ($this->newParents as $parent) {
            DB::table('accounts')->insertOrIgnore([
                'account_code' => $parent['code'],
                'account_name' => $parent['name'],
                'account_type' => 'expense',
                'parent_id'    => $expensesParentId,
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        foreach ($this->reassignments as $childCode => $parentCode) {
            $parentId = DB::table('accounts')->where('account_code', $parentCode)->value('id');
            if ($parentId) {
                DB::table('accounts')->where('account_code', $childCode)->update(['parent_id' => $parentId]);
            }
        }
    }

    public function down(): void
    {
        $expensesParentId = DB::table('accounts')->where('account_code', '5000')->value('id');

        foreach ($this->reassignments as $childCode => $parentCode) {
            DB::table('accounts')->where('account_code', $childCode)->update(['parent_id' => $expensesParentId]);
        }

        DB::table('accounts')->whereIn('account_code', array_column($this->newParents, 'code'))->delete();
    }
};
