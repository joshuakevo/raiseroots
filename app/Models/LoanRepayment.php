<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranchViaRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use HasFactory, ScopedToBranchViaRelation;

    protected static string $branchScopeRelation = 'loan';

    protected $fillable = [
        'loan_id', 'payment_date', 'amount', 'principal_paid', 'interest_paid',
        'penalty_paid', 'payment_method', 'payment_source_account_id', 'reference', 'received_by', 'transaction_id', 'notes',
    ];

    protected $casts = [
        'payment_date'   => 'date',
        'amount'         => 'float',
        'principal_paid' => 'float',
        'interest_paid'  => 'float',
        'penalty_paid'   => 'float',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
