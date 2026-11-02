<?php

namespace App\Integrations\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the application log. Phone confirmation uses this
 * channel even after a platform SMS provider is configured. Platform messages
 * go through SmsTransmitter, which also logs when no provider is active.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS provider is not configured. Message logged so the technical team can deliver it.', [
            'to' => $to,
            'body' => $message,
        ]);
    }
}
