<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model {
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'savings_account_id',
        'basic_salary', 'allowances', 'gross_salary', 'paye', 'nssf_employee', 'nssf_employer',
        'deductions', 'net_salary',
    ];

    protected $casts = [
        'basic_salary' => 'float',
        'allowances'   => 'float',
        'gross_salary'  => 'float',
        'paye'          => 'float',
        'nssf_employee' => 'float',
        'nssf_employer' => 'float',
        'deductions'   => 'float',
        'net_salary'   => 'float',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function payrollRun() { return $this->belongsTo(PayrollRun::class); }
    public function savingsAccount() { return $this->belongsTo(SavingsAccount::class); }
}
