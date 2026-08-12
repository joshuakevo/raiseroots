<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanCollateral extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'household'               => 'Household',
        'land_sale_agreement'     => 'Land Sale Agreement',
        'lc1_introduction_letter' => 'LC1 Introduction Letter',
        'land_titles'             => 'Land Titles',
        'motor_vehicles'          => 'Motor Vehicles',
        'post_dated_cheques'      => 'Post Dated Cheques',
    ];

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
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
