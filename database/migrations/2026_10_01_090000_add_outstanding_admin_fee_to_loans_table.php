<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Cost is now part of the loan balance, collected via ordinary repayments
 * (priority: penalty -> interest -> admin fee -> principal), not tracked separately.
 * `admin_cost` (added earlier) stays the original amount charged, unchanged once set -
 * this is the running "still owed" figure, same pattern as outstanding_principal vs
 * principal. Starts at 0; set to admin_cost at disbursement, decremented by repayments
 * and by Record Admin Fee.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('outstanding_admin_fee', 15, 2)->default(0)->after('admin_cost');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('outstanding_admin_fee');
        });
    }
};
