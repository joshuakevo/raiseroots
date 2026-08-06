<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','approved','active','closed','defaulted') NOT NULL DEFAULT 'pending'");

        Schema::table('loan_guarantors', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('monthly_income');
        });
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loans MODIFY COLUMN status ENUM('pending','active','closed','defaulted') NOT NULL DEFAULT 'pending'");

        Schema::table('loan_guarantors', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
