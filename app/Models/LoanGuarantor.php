<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanGuarantor extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'client_id', 'name', 'phone', 'id_number',
        'relationship', 'address', 'employer', 'monthly_income', 'photo',
    ];

    protected $casts = ['monthly_income' => 'float'];

    public function loan()   { return $this->belongsTo(Loan::class); }
    public function client() { return $this->belongsTo(Client::class); }
}
