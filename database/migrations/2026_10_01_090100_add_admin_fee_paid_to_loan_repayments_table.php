<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How much of a given repayment went to Admin Fee - same pattern as principal_paid/interest_paid/penalty_paid. */
return new class extends Migration
{
    public function up()
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->decimal('admin_fee_paid', 15, 2)->default(0)->after('penalty_paid');
        });
    }

    public function down()
    {
        Schema::table('loan_repayments', function (Blueprint $table) {
            $table->dropColumn('admin_fee_paid');
        });
    }
};
