<?php

namespace App\Contracts;

/**
 * Outbound SMS. The log adapter is the delivery channel until a provider is configured.
 */
interface SmsSender
{
    public function send(string $to, string $message): void;
}
