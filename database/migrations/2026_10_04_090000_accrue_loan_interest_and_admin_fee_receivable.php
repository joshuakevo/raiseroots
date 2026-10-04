<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loan interest + admin fee are now carried in Loan Receivables from disbursement
 * (DR receivable / CR 2006 Unearned Interest & Fees), and moved from 2006 into income
 * as they're paid. `income_accrued` marks loans whose GL carries that accrual, so
 * repayments on older loans (not yet accrued) keep posting the old way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->boolean('income_accrued')->default(false)->after('outstanding_admin_fee');
        });

        if (!DB::table('accounts')->where('account_code', '2006')->exists()) {
            DB::table('accounts')->insert([
                'account_code' => '2006',
                'account_name' => 'Unearned Interest & Fees',
                'account_type' => 'liability',
                'parent_id'    => DB::table('accounts')->where('account_code', '2000')->value('id'),
                'is_active'    => true,
                'description'  => 'Loan interest and admin fees owed but not yet paid; moved to income as collected.',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('income_accrued');
        });
    }
};
