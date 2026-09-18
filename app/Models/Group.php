<?php

namespace App\Models;

use App\Models\Concerns\ScopedToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasFactory, SoftDeletes, ScopedToBranch;

    protected $table = 'groups';

    protected $fillable = [
        'client_id', 'branch_id', 'group_number', 'name', 'group_type', 'registration_date',
        'membership_fee', 'expected_contribution', 'contribution_cycle', 'pool_balance',
        'monthly_interest_rate', 'gl_account_id', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'registration_date'    => 'date',
        'membership_fee'       => 'decimal:2',
        'monthly_interest_rate'=> 'decimal:4',
        'expected_contribution'=> 'decimal:2',
        'pool_balance'         => 'decimal:2',
    ];

    public function isPooled(): bool
    {
        return $this->group_type === 'pooled';
    }

    public function members()
    {
        return $this->hasMany(GroupMember::class);
    }

    public function activeMembers()
    {
        return $this->hasMany(GroupMember::class)->where('status', 'active');
    }

    public function leader()
    {
        return $this->hasOne(GroupMember::class)->where('is_leader', true);
    }

    public function transactions()
    {
        return $this->hasMany(GroupTransaction::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalBalanceAttribute(): float
    {
        return (float) $this->activeMembers()->sum('balance');
    }

    public static function generateGroupNumber(): string
    {
        $last = static::withoutGlobalScope('branch')->withTrashed()->orderByDesc('id')->value('group_number');
        $next = $last ? ((int) substr($last, 2)) + 1 : 1;
        return 'GR' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}
