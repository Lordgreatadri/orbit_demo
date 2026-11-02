<?php

namespace App\Integrations\Sms;

use App\Enums\SmsMessageStatus;

final class NormalizedCallback
{
    public function __construct(
        public readonly string $eventKey,
        public readonly ?SmsMessageStatus $status,
        public readonly string $providerStatus,
        public readonly ?string $correlationId,
        public readonly ?string $messageUuid = null,
        public readonly ?string $reason = null,
    ) {}
}
