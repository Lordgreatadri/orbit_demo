<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessageEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'sms_message_id',
        'sms_provider_id',
        'provider_key',
        'type',
        'status',
        'provider_status',
        'event_key',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class, 'sms_message_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class, 'sms_provider_id');
    }
}
