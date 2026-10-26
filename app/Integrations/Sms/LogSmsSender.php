<?php

namespace App\Integrations\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the application log. No SMS provider is called.
 * Replace this binding when a provider adapter is approved (ADR-0007).
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
