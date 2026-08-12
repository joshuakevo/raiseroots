<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number', 'client_id', 'name', 'phone', 'email', 'id_number',
        'position', 'department', 'basic_salary',
        'payment_method', 'savings_account_id', 'payment_source_account_id',
        'status', 'notes', 'created_by',
    ];

    protected $casts = ['basic_salary' => 'float'];

    public function client() {
        return $this->belongsTo(Client::class);
    }
    public function savingsAccount() {
        return $this->belongsTo(SavingsAccount::class);
    }
    public function paymentSourceAccount() {
        return $this->belongsTo(Account::class, 'payment_source_account_id');
    }
    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function payrollItems() {
        return $this->hasMany(PayrollItem::class);
    }
}
