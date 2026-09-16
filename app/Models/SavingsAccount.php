<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsAccount extends Model
{
    use HasFactory, SoftDeletes, ScopedToBranch;

    protected $fillable = [
        'client_id', 'branch_id', 'product_id', 'account_number', 'balance', 'status', 'opened_date', 'created_by',
        'last_interest_date',
    ];

    protected $casts = [
        'balance'            => 'float',
        'opened_date'        => 'date',
        'last_interest_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(SavingsProduct::class, 'product_id');
    }

    public function transactions()
    {
        return $this->hasMany(SavingsTransaction::class)->orderBy('transaction_date', 'desc');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canWithdraw(float $amount): bool
    {
        $minBalance = $this->product->minimum_balance ?? 0;
        return ($this->balance - $amount) >= $minBalance;
    }
}
