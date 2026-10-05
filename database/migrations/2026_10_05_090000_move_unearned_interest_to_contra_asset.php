<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unearned Interest & Fees moves from a liability (2006) to a contra-asset (1199) directly
 * under Loan Receivables, so the balance sheet shows receivables (principal + interest +
 * admin fee) less what's not yet earned, with nothing under Liabilities. Same account row,
 * so every line already posted to it moves with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->where('account_code', '2006')->update([
            'account_code' => '1199',
            'account_name' => 'Less: Unearned Interest & Fees',
            'account_type' => 'asset',
            'parent_id'    => DB::table('accounts')->where('account_code', '1100')->value('id'),
            'description'  => 'Contra to Loan Receivables: interest and admin fees owed but not yet paid; moved to income as collected.',
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('accounts')->where('account_code', '1199')->update([
            'account_code' => '2006',
            'account_name' => 'Unearned Interest & Fees',
            'account_type' => 'liability',
            'parent_id'    => DB::table('accounts')->where('account_code', '2000')->value('id'),
            'updated_at'   => now(),
        ]);
    }
};
