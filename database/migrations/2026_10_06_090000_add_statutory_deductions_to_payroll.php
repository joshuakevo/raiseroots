<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PAYE and NSSF on payroll (PayrollTaxService). Each item keeps its breakdown; processing
 * posts PAYE and NSSF (employee 5% + employer 10%) to payable accounts until remitted to
 * URA / NSSF, and the employer's 10% to its own expense account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('gross_salary', 15, 2)->default(0)->after('allowances');
            $table->decimal('paye', 15, 2)->default(0)->after('gross_salary');
            $table->decimal('nssf_employee', 15, 2)->default(0)->after('paye');
            $table->decimal('nssf_employer', 15, 2)->default(0)->after('nssf_employee');
        });

        $liabilities = DB::table('accounts')->where('account_code', '2000')->value('id');
        $expenses    = DB::table('accounts')->where('account_code', '5000')->value('id');
        foreach ([
            ['2007', 'PAYE Payable',                 'liability', $liabilities, 'PAYE withheld from salaries, owed to URA.'],
            ['2008', 'NSSF Payable',                 'liability', $liabilities, 'NSSF contributions (employee 5% + employer 10%), owed to NSSF.'],
            ['5011', 'Employer NSSF Contribution',   'expense',   $expenses,    "The company's 10% NSSF contribution on salaries."],
        ] as [$code, $name, $type, $parent, $desc]) {
            if (!DB::table('accounts')->where('account_code', $code)->exists()) {
                DB::table('accounts')->insert([
                    'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
                    'parent_id' => $parent, 'is_active' => true, 'description' => $desc,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['gross_salary', 'paye', 'nssf_employee', 'nssf_employer']);
        });
    }
};
