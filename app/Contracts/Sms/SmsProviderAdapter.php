<?php

namespace App\Contracts\Sms;

use App\Integrations\Sms\BalanceSnapshot;
use App\Integrations\Sms\DeliveryOutcome;
use App\Integrations\Sms\NormalizedCallback;
use App\Models\SmsMessage;
use App\Models\SmsProvider;

/**
 * One provider adapter. Register it in SmsProviderRegistry; routes, webhook
 * config, and the admin tabs are built from that registry.
 */
interface SmsProviderAdapter
{
    public function key(): string;

    public function name(): string;

    public function summary(): string;

    public function documentationUrl(): string;

    /** Date the live provider documentation was reviewed (Y-m-d). */
    public function reviewedOn(): string;

    public function defaultRequestsPerMinute(): int;

    public function supportsSandbox(): bool;

    /** The provider reports an SMS credit bundle that can be watched on its own. */
    public function tracksSmsBundle(): bool;

    /** The provider reports a cash or account balance that can be watched on its own. */
    public function tracksCashBalance(): bool;

    public function webhookName(): string;

    /**
     * @return list<string>
     */
    public function webhookMethods(): array;

    /**
     * @return class-string
     */
    public function signatureValidator(): string;

    /**
     * @return class-string
     */
    public function processWebhookJob(): string;

    /**
     * @return list<array{key: string, label: string, secret: bool, hint: string}>
     */
    public function credentialFields(): array;

    /**
     * Provider HTTP calls this set will make. One call may cover many recipients.
     * The per-minute guard counts these calls, not the recipients.
     *
     * @param  list<SmsMessage>  $messages
     */
    public function transmissionRequests(array $messages): int;

    /**
     * @param  list<SmsMessage>  $messages
     * @return list<DeliveryOutcome>
     */
    public function deliver(SmsProvider $provider, array $messages): array;

    public function balance(SmsProvider $provider): BalanceSnapshot;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function normalizeCallback(array $payload): ?NormalizedCallback;
}
