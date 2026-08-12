<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name')->nullable()->after('client_id');
            $table->string('phone')->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('id_number')->nullable()->after('email');
            $table->enum('payment_method', ['savings', 'cash'])->default('cash')->after('savings_account_id');
            $table->foreignId('payment_source_account_id')->nullable()->after('payment_method')
                ->constrained('accounts')->nullOnDelete();
        });

        // Backfill: existing employees only ever had a name via their linked client.
        DB::statement('
            UPDATE employees
            JOIN clients ON clients.id = employees.client_id
            SET employees.name = clients.name
            WHERE employees.client_id IS NOT NULL
        ');

        // Every pre-existing employee was paid via a savings account — preserve that.
        DB::table('employees')->whereNotNull('savings_account_id')->update(['payment_method' => 'savings']);

        DB::statement("ALTER TABLE employees MODIFY COLUMN name VARCHAR(255) NOT NULL");
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['payment_source_account_id']);
            $table->dropColumn(['name', 'phone', 'email', 'id_number', 'payment_method', 'payment_source_account_id']);
        });
    }
};
