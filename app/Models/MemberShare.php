<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranchViaRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberShare extends Model
{
    use HasFactory, ScopedToBranchViaRelation;

    protected static string $branchScopeRelation = 'client';

    protected $fillable = [
        'client_id', 'share_number', 'share_value', 'amount_paid', 'status', 'notes', 'created_by',
        'liquidated_at', 'liquidation_notes', 'liquidated_by',
    ];

    protected $casts = [
        'liquidated_at' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function liquidatedBy()
    {
        return $this->belongsTo(User::class, 'liquidated_by');
    }

    public function transactions()
    {
        return $this->hasMany(ShareTransaction::class, 'share_id')->orderBy('transaction_date')->orderBy('id');
    }

    public function getBalanceAttribute(): float
    {
        return $this->share_value - $this->amount_paid;
    }

    public function isLiquidated(): bool
    {
        return $this->status === 'liquidated';
    }
}
