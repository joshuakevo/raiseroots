<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranchViaRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanSchedule extends Model
{
    use HasFactory, ScopedToBranchViaRelation;

    protected static string $branchScopeRelation = 'loan';

    protected $fillable = [
        'loan_id', 'installment_no', 'due_date', 'principal_due', 'interest_due',
        'total_due', 'balance_after', 'principal_paid', 'interest_paid', 'status',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'principal_due'  => 'float',
        'interest_due'   => 'float',
        'total_due'      => 'float',
        'balance_after'  => 'float',
        'principal_paid' => 'float',
        'interest_paid'  => 'float',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function getPrincipalBalanceAttribute(): float
    {
        return $this->principal_due - $this->principal_paid;
    }

    public function getInterestBalanceAttribute(): float
    {
        return $this->interest_due - $this->interest_paid;
    }

    public function isOverdue(): bool
    {
        return $this->due_date->isPast() && $this->status !== 'paid';
    }
}
