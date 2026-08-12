<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_number', 'client_id', 'loan_product_id', 'principal', 'interest_rate',
        'interest_method', 'repayment_frequency', 'term_months', 'disbursement_date', 'maturity_date',
        'outstanding_principal', 'outstanding_interest', 'outstanding_penalty',
        'application_fee', 'application_fee_rate', 'application_fee_method',
        'management_fee', 'management_fee_rate', 'management_fee_method',
        'insurance_fee', 'insurance_fee_rate', 'insurance_fee_method',
        'fee_savings_account_id',
        'status', 'approved_by', 'approved_at', 'created_by', 'notes',
    ];

    protected $casts = [
        'principal'             => 'float',
        'interest_rate'         => 'float',
        'outstanding_principal' => 'float',
        'outstanding_interest'  => 'float',
        'outstanding_penalty'   => 'float',
        'application_fee'       => 'float',
        'application_fee_rate'  => 'float',
        'management_fee'        => 'float',
        'management_fee_rate'   => 'float',
        'insurance_fee'         => 'float',
        'insurance_fee_rate'    => 'float',
        'disbursement_date'     => 'date',
        'maturity_date'         => 'date',
        'approved_at'           => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function schedules()
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('installment_no');
    }

    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class)->orderBy('payment_date');
    }

    public function guarantors()
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    public function collaterals()
    {
        return $this->hasMany(LoanCollateral::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function feeSavingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class, 'fee_savings_account_id');
    }

    public function getTotalFeesAttribute(): float
    {
        return $this->application_fee + $this->management_fee + $this->insurance_fee;
    }

    public function getTotalOutstandingAttribute(): float
    {
        return $this->outstanding_principal + $this->outstanding_interest + $this->outstanding_penalty;
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->repayments()->sum('amount');
    }

    public function isOverdue(): bool
    {
        if ($this->status !== 'active') return false;
        return $this->schedules()
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', 'paid')
            ->exists();
    }
}
