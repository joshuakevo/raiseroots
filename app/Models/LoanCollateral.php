<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanCollateral extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id', 'category', 'description', 'file_path', 'file_type', 'created_by',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('loan_collateral_categories')) {
            return $this->category;
        }

        return LoanCollateralCategory::where('key', $this->category)->value('label') ?? $this->category;
    }
}
