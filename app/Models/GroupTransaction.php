<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranchViaRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupTransaction extends Model
{
    use HasFactory, ScopedToBranchViaRelation;

    protected static string $branchScopeRelation = 'group';

    protected $fillable = [
        'group_id', 'member_id', 'type', 'posting_type',
        'amount', 'balance_before', 'balance_after',
        'transaction_date', 'reference', 'notes',
        'journal_transaction_id', 'created_by',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'balance_before'  => 'decimal:2',
        'balance_after'   => 'decimal:2',
        'transaction_date'=> 'date',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'member_id');
    }

    public function journalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'journal_transaction_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
