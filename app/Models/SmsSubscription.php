<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount', 'phone_number', 'reference', 'transaction_uuid',
        'status', 'period_start', 'period_end', 'raw_response', 'initiated_by',
    ];

    protected $casts = [
        'amount'       => 'float',
        'period_start' => 'date',
        'period_end'   => 'date',
    ];

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'completed'
            && $this->period_end !== null
            && today()->lessThanOrEqualTo($this->period_end);
    }
}
