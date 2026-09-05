<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_number', 'client_type', 'name', 'branch_id', 'created_by', 'relationship_manager_id',
        'first_name', 'middle_name', 'last_name',
        'gender', 'date_of_birth', 'marital_status', 'nationality', 'id_number', 'photo',
        'phone', 'alt_phone', 'email', 'address', 'district', 'village', 'postal_address',
        'employment_status', 'purpose_of_joining', 'expected_monthly_savings', 'loan_interest',
        'membership_fee', 'membership_fee_paid', 'membership_fee_status',
        'next_of_kin_name', 'next_of_kin_relationship', 'next_of_kin_phone', 'next_of_kin_address',
        'preferred_communication', 'status', 'joining_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date'  => 'date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function relationshipManager()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'relationship_manager_id');
    }

    /** Falls back to whoever created the client until an RM is explicitly assigned. */
    public function getRelationshipManagerNameAttribute(): ?string
    {
        return $this->relationshipManager?->name ?? $this->createdBy?->name;
    }

    public function portalUsers()
    {
        return $this->belongsToMany(\App\Models\User::class, 'client_portal_users');
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function savingsAccounts()
    {
        return $this->hasMany(SavingsAccount::class);
    }

    public function fixedDeposits()
    {
        return $this->hasMany(FixedDeposit::class);
    }

    public function activeLoans()
    {
        return $this->hasMany(Loan::class)->whereIn('status', ['active', 'defaulted']);
    }

    public function activeSavingsAccounts()
    {
        return $this->hasMany(SavingsAccount::class)->where('status', 'active');
    }

    public function shares()
    {
        return $this->hasMany(MemberShare::class);
    }

    public function group()
    {
        return $this->hasOne(Group::class);
    }

    public function isGroup(): bool
    {
        return $this->client_type === 'group';
    }

    public function getMembershipFeeBalanceAttribute(): float
    {
        return $this->membership_fee - $this->membership_fee_paid;
    }
}
