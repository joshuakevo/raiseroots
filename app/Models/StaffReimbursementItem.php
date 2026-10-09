<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffReimbursementItem extends Model
{
    protected $fillable = [
        'staff_reimbursement_run_id', 'employee_id', 'savings_account_id',
        'description', 'amount', 'deduction', 'deduction_reason', 'net_amount',
    ];

    protected $casts = [
        'amount'     => 'float',
        'deduction'  => 'float',
        'net_amount' => 'float',
    ];

    public function run() { return $this->belongsTo(StaffReimbursementRun::class, 'staff_reimbursement_run_id'); }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function savingsAccount() { return $this->belongsTo(SavingsAccount::class); }
}
