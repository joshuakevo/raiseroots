<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts the previous migration's disbursed_by column - the Audit Log
 * already tracks who disbursed a loan, so this was redundant. Added as a
 * new migration (rather than editing/deleting the add-migration) since
 * that one may already have run on production; this way it's safe to
 * deploy regardless of whether it has.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('loans', 'disbursed_by')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropForeign(['disbursed_by']);
                $table->dropColumn('disbursed_by');
            });
        }
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('disbursed_by')->nullable()->after('approved_by');
            $table->foreign('disbursed_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
