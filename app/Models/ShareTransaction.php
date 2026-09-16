<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranchViaRelation;
use Illuminate\Database\Eloquent\Model;

class ShareTransaction extends Model
{
    use ScopedToBranchViaRelation;

    protected static string $branchScopeRelation = 'client';

    protected $fillable = [
        'share_id', 'client_id', 'type', 'amount', 'old_value', 'new_value', 'amount_paid_before',
        'transaction_date', 'reference', 'payment_method', 'notes',
        'journal_transaction_id', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function share()
    {
        return $this->belongsTo(MemberShare::class, 'share_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
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
