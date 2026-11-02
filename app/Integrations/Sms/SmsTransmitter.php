<?php

namespace App\Integrations\Sms;

use App\Enums\SmsMessageStatus;
use App\Jobs\Sms\DeliverSmsMessages;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Platform-wide SMS entry point.
 *
 * The platform pays for these messages. counts_toward_subscription is always
 * false so a later organisation subscription module does not bill them.
 * Phone confirmation keeps using App\Contracts\SmsSender and does not call here.
 * With no enabled active provider, the message is stored and written to the log.
 */
final class SmsTransmitter
{
    public function __construct(private readonly SmsProviderRegistry $registry) {}

    public function transmit(
        string $to,
        string $body,
        string $idempotencyKey,
        string $purpose = 'platform_notification',
        ?int $organizationId = null,
        ?SmsProvider $provider = null,
    ): SmsMessage {
        return $this->transmitMany([[
            'to' => $to,
            'body' => $body,
            'idempotency_key' => $idempotencyKey,
        ]], $purpose, $organizationId, $provider)[0];
    }

    /**
     * @param  list<array{to: string, body: string, idempotency_key: string}>  $items
     * @return list<SmsMessage>
     */
    public function transmitMany(
        array $items,
        string $purpose = 'platform_notification',
        ?int $organizationId = null,
        ?SmsProvider $provider = null,
    ): array {
        if ($items === []) {
            throw new InvalidArgumentException('At least one message is required.');
        }

        if ($provider !== null && ! $provider->enabled) {
            throw new InvalidArgumentException('That SMS provider is turned off.');
        }

        $active = $provider ?? SmsProvider::query()
            ->where('is_active', true)
            ->where('enabled', true)
            ->first();

        $messages = [];
        $freshIds = [];

        foreach ($items as $item) {
            $phone = E164::assert($item['to']);
            $body = trim($item['body']);

            if ($body === '' || mb_strlen($body) > 1000) {
                throw new InvalidArgumentException('Message text must be between 1 and 1000 characters.');
            }

            $key = mb_substr(trim($item['idempotency_key']), 0, 80);

            if ($key === '') {
                throw new InvalidArgumentException('An idempotency key is required.');
            }

            $existing = SmsMessage::query()->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                $messages[] = $existing;

                continue;
            }

            try {
                $message = SmsMessage::query()->create([
                    'organization_id' => $organizationId,
                    'sms_provider_id' => $active?->id,
                    'idempotency_key' => $key,
                    'to' => $phone,
                    'body' => $body,
                    'sender_id' => $active?->sender_id,
                    'purpose' => mb_substr($purpose, 0, 40),
                    'status' => SmsMessageStatus::Queued,
                    'attempts' => 0,
                    'max_attempts' => $active?->max_attempts ?? 1,
                    'backoff_seconds' => $active?->backoffSchedule() ?? [30, 120, 300],
                    'allow_fallback' => $provider === null && $purpose !== 'operator_test',
                    'used_fallback' => false,
                    'counts_toward_subscription' => false,
                    'billing_scope' => 'platform',
                    'queued_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $messages[] = SmsMessage::query()->where('idempotency_key', $key)->firstOrFail();

                continue;
            }

            $messages[] = $message;
            $freshIds[] = $message->id;
        }

        if ($freshIds === []) {
            return $messages;
        }

        if ($active === null) {
            foreach ($messages as $message) {
                if (! in_array($message->id, $freshIds, true)) {
                    continue;
                }

                $message->markLogged();
                Log::info('SMS transmission logged because no provider is active.', [
                    'sms_message_uuid' => $message->uuid,
                    'to' => $message->to,
                    'purpose' => $message->purpose,
                    'body' => $message->body,
                ]);
            }

            return $messages;
        }

        DeliverSmsMessages::dispatch($freshIds);

        return $messages;
    }
}
