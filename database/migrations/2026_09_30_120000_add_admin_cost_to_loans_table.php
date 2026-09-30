<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Cost is captured at disbursement (default 12.2% of principal, editable) and
 * stored here for reference/record-keeping only - it's expected but not yet collected,
 * so no GL entry is posted for it at disbursement time. It gets posted (e.g. via a
 * manual Journal Entry, debiting Cash / crediting GL 4009 - Loan Administrative Fee)
 * only once it's actually paid, whenever that happens.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('admin_cost', 15, 2)->default(0)->after('insurance_fee_method');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('admin_cost');
        });
    }
};
