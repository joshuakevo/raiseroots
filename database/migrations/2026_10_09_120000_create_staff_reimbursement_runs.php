<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff reimbursements as runs, like payroll: one run lists many staff, each with an
 * amount, an optional deduction and the net paid; processing pays everyone in one
 * journal to their usual salary payout (cash/bank account or savings). Replaces the
 * one-at-a-time staff_reimbursements screen (that table is kept for any old records).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_reimbursement_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number')->unique();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            $table->string('description', 200)->nullable();
            $table->foreignId('expense_account_id')->constrained('accounts');
            $table->foreignId('deduction_account_id')->nullable()->constrained('accounts');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft | processed
            $table->date('payment_date')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('staff_reimbursement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_reimbursement_run_id')->constrained('staff_reimbursement_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('savings_account_id')->nullable()->constrained('savings_accounts');
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('deduction', 15, 2)->default(0);
            $table->string('deduction_reason', 255)->nullable();
            $table->decimal('net_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_reimbursement_items');
        Schema::dropIfExists('staff_reimbursement_runs');
    }
};
