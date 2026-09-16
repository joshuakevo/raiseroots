<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory, ScopedToBranch;

    protected $fillable = [
        'date', 'reference', 'description', 'module', 'module_id', 'created_by',
        'reversal_of', 'reversed_by', 'reversal_reason', 'branch_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function lines()
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalDebitAttribute(): float
    {
        return $this->lines()->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->lines()->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.001;
    }

    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversal_of');
    }

    public function reversalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversed_by');
    }

    public function isReversed(): bool
    {
        return !is_null($this->reversed_by);
    }

    public function isReversal(): bool
    {
        return !is_null($this->reversal_of);
    }
}
