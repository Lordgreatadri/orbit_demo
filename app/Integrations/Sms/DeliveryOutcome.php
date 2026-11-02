<?php

namespace App\Integrations\Sms;

final class DeliveryOutcome
{
    /**
     * @param  'accepted'|'delivered'|'failed'|'retry'  $result
     */
    public function __construct(
        public readonly int $localId,
        public readonly string $result,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $providerStatus = null,
        public readonly ?string $error = null,
    ) {}
}
