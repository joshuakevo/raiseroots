<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loan_products MODIFY COLUMN repayment_frequency ENUM('daily','weekly','monthly','quarterly','annually') NOT NULL DEFAULT 'monthly'");
        DB::statement("ALTER TABLE loans MODIFY COLUMN repayment_frequency ENUM('daily','weekly','monthly','quarterly','annually') NOT NULL DEFAULT 'monthly'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loan_products MODIFY COLUMN repayment_frequency ENUM('monthly','quarterly') NOT NULL DEFAULT 'monthly'");
        DB::statement("ALTER TABLE loans MODIFY COLUMN repayment_frequency ENUM('monthly','quarterly') NOT NULL DEFAULT 'monthly'");
    }
};
