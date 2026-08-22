<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LoanCollateralCategory extends Model
{
    protected $fillable = ['key', 'label', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Active categories as [key => label], or empty if the table hasn't been
     * migrated yet on this deploy — safe to call anywhere, including before
     * "Run Migrations" has been clicked on Settings.
     */
    public static function activeOptions(): Collection
    {
        if (! Schema::hasTable('loan_collateral_categories')) {
            return collect();
        }

        return static::active()->orderBy('label')->pluck('label', 'key');
    }
}
