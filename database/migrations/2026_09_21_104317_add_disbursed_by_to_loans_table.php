<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->unsignedBigInteger('disbursed_by')->nullable()->after('approved_by');
            $table->foreign('disbursed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['disbursed_by']);
            $table->dropColumn('disbursed_by');
        });
    }
};
