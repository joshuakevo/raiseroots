<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedDeposit extends Model
{
    use HasFactory, SoftDeletes, ScopedToBranch;

    protected $fillable = [
        'client_id', 'branch_id', 'savings_account_id', 'product_id', 'deposit_number', 'principal', 'interest_rate',
        'term_months', 'start_date', 'maturity_date', 'interest_amount', 'maturity_amount',
        'accrued_interest', 'status', 'closed_date', 'payment_source_account_id', 'created_by',
    ];

    protected $casts = [
        'principal'       => 'float',
        'interest_rate'   => 'float',
        'interest_amount' => 'float',
        'maturity_amount' => 'float',
        'accrued_interest'=> 'float',
        'start_date'      => 'date',
        'maturity_date'   => 'date',
        'closed_date'     => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function product()
    {
        return $this->belongsTo(FixedDepositProduct::class, 'product_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isMatured(): bool
    {
        return $this->maturity_date->isPast() || $this->maturity_date->isToday();
    }

    public function daysToMaturity(): int
    {
        return max(0, now()->diffInDays($this->maturity_date, false));
    }
}
