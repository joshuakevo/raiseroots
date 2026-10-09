<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('tin_number', 30)->nullable()->after('nssf_number');
            $table->string('bank_name', 100)->nullable()->after('tin_number');
            $table->string('bank_account_number', 50)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['tin_number', 'bank_name', 'bank_account_number']);
        });
    }
};
