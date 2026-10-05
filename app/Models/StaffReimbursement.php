<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffReimbursement extends Model
{
    protected $fillable = [
        'reference', 'employee_id', 'date', 'description', 'expense_account_id',
        'amount', 'deduction', 'deduction_reason', 'deduction_account_id', 'net_amount',
        'payment_method', 'payment_account_id', 'savings_account_id',
        'transaction_id', 'status', 'created_by',
    ];

    protected $casts = [
        'date'       => 'date',
        'amount'     => 'float',
        'deduction'  => 'float',
        'net_amount' => 'float',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function expenseAccount() { return $this->belongsTo(Account::class, 'expense_account_id'); }
    public function deductionAccount() { return $this->belongsTo(Account::class, 'deduction_account_id'); }
    public function paymentAccount() { return $this->belongsTo(Account::class, 'payment_account_id'); }
    public function savingsAccount() { return $this->belongsTo(SavingsAccount::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
