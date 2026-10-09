<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffReimbursementRun extends Model
{
    protected $fillable = [
        'run_number', 'period_month', 'period_year', 'description',
        'expense_account_id', 'deduction_account_id',
        'total_amount', 'total_deduction', 'total_net',
        'status', 'payment_date', 'transaction_id', 'processed_by', 'processed_at', 'created_by',
    ];

    protected $casts = [
        'total_amount'    => 'float',
        'total_deduction' => 'float',
        'total_net'       => 'float',
        'payment_date'    => 'date',
        'processed_at'    => 'datetime',
    ];

    public function items() { return $this->hasMany(StaffReimbursementItem::class); }
    public function expenseAccount() { return $this->belongsTo(Account::class, 'expense_account_id'); }
    public function deductionAccount() { return $this->belongsTo(Account::class, 'deduction_account_id'); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function processedBy() { return $this->belongsTo(User::class, 'processed_by'); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    public function getPeriodLabelAttribute(): string
    {
        return date('F', mktime(0, 0, 0, $this->period_month, 1)) . ' ' . $this->period_year;
    }
}
