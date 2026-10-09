<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-organisation cap on the loan amount a manager (anyone below admin) may approve,
 * e.g. Sipmart 1,000,000, Raiseroots 500,000. 0 / blank = no limit. Admins are never capped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key'        => 'manager_loan_approval_limit',
            'value'      => '0',
            'group'      => 'financial',
            'label'      => 'Manager Loan Approval Limit (0 = no limit; admins can approve any amount)',
            'type'       => 'number',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'manager_loan_approval_limit')->delete();
    }
};
