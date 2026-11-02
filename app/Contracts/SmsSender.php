<?php

namespace App\Contracts;

/**
 * Phone-confirmation SMS. This stays on the log adapter.
 * Platform messages use App\Integrations\Sms\SmsTransmitter instead.
 */
interface SmsSender
{
    public function send(string $to, string $message): void;
}
