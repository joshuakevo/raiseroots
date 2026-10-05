<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff reimbursements: an employee claims an amount, an optional deduction is taken
 * off (e.g. an advance already given, or a part not allowed), and the net is paid
 * immediately - to a cash/bank account or the employee's savings account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_reimbursements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('date');
            $table->string('description', 255);
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->decimal('amount', 15, 2);
            $table->decimal('deduction', 15, 2)->default(0);
            $table->string('deduction_reason', 255)->nullable();
            $table->foreignId('deduction_account_id')->nullable()->constrained('accounts');
            $table->decimal('net_amount', 15, 2);
            $table->string('payment_method', 20); // cash | savings
            $table->foreignId('payment_account_id')->nullable()->constrained('accounts');
            $table->foreignId('savings_account_id')->nullable()->constrained('savings_accounts');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->string('status', 20)->default('paid'); // paid | reversed
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_reimbursements');
    }
};
