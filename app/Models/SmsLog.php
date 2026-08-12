<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_reference', 'client_id', 'client_name', 'phone',
        'recipient_group', 'message', 'status', 'response', 'sent_by',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
