<?php

namespace App\Integrations\Sms;

use App\Enums\SmsProviderHealth;

final class BalanceSnapshot
{
    public function __construct(
        public readonly SmsProviderHealth $health,
        public readonly string $message,
        public readonly ?string $amount = null,
        public readonly ?string $unit = null,
        public readonly ?string $cashAmount = null,
        public readonly ?string $cashUnit = null,
        public readonly ?string $bundleAmount = null,
        public readonly ?string $bundleUnit = null,
    ) {}
}
