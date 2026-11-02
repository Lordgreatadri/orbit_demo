<?php

namespace App\Integrations\Sms\Webhooks;

use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\SmsLog;
use App\Integrations\Sms\SmsProviderRegistry;
use App\Integrations\Sms\SmsText;
use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SmsCallbackApplier
{
    public function __construct(private readonly SmsProviderRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apply(string $providerKey, array $payload): void
    {
        $callback = $this->registry->get($providerKey)->normalizeCallback($payload);

        if ($callback === null) {
            SmsLog::provider($providerKey)->info('SMS callback was ignored.', [
                'provider' => $providerKey,
                'reason' => 'unreadable',
            ]);

            return;
        }

        $message = $this->findMessage($providerKey, $callback->messageUuid, $callback->correlationId);

        if ($message === null) {
            SmsLog::provider($providerKey)->info('SMS callback was ignored.', [
                'provider' => $providerKey,
                'reason' => 'unknown message',
                'provider_status' => SmsMessage::shortStatus($callback->providerStatus),
            ]);

            return;
        }

        $safePayload = array_filter([
            'provider_status' => SmsMessage::shortStatus($callback->providerStatus),
            'reason' => $callback->reason !== null ? SmsText::safe($callback->reason) : null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            DB::transaction(function () use ($message, $providerKey, $callback, $safePayload): void {
                $exists = SmsMessageEvent::query()
                    ->where('provider_key', $providerKey)
                    ->where('event_key', $callback->eventKey)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    SmsLog::provider($providerKey)->info('SMS callback was ignored.', [
                        'provider' => $providerKey,
                        'reason' => 'duplicate',
                        'message' => $message->uuid,
                    ]);

                    return;
                }

                SmsMessageEvent::query()->create([
                    'sms_message_id' => $message->id,
                    'sms_provider_id' => $message->sms_provider_id,
                    'provider_key' => $providerKey,
                    'type' => 'callback',
                    'status' => $callback->status?->value,
                    'provider_status' => SmsMessage::shortStatus($callback->providerStatus),
                    'event_key' => mb_substr($callback->eventKey, 0, 160),
                    'payload' => $safePayload,
                ]);

                $message->refresh();

                if ($callback->status === null) {
                    return;
                }

                match ($callback->status) {
                    SmsMessageStatus::Delivered => $message->markDelivered($callback->providerStatus),
                    SmsMessageStatus::Failed => $message->markFailed($callback->reason ?: 'The provider reported a failure.', $callback->providerStatus),
                    SmsMessageStatus::Accepted => $message->markAccepted($callback->correlationId, $callback->providerStatus),
                    default => null,
                };

                SmsLog::provider($providerKey)->info('SMS callback applied.', [
                    'provider' => $providerKey,
                    'message' => $message->uuid,
                    'status' => $callback->status?->value,
                    'provider_status' => SmsMessage::shortStatus($callback->providerStatus),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // A duplicate callback already recorded this event.
        }
    }

    private function findMessage(string $providerKey, ?string $uuid, ?string $correlationId): ?SmsMessage
    {
        if (is_string($uuid) && Str::isUuid($uuid)) {
            $message = $this->forProvider($providerKey)->where('uuid', $uuid)->first();

            if ($message !== null) {
                return $message;
            }
        }

        if ($correlationId === null || $correlationId === '') {
            return null;
        }

        return $this->forProvider($providerKey)
            ->where(function (Builder $query) use ($correlationId): void {
                $query->where('provider_message_id', $correlationId);

                if (Str::isUuid($correlationId)) {
                    $query->orWhere('uuid', $correlationId);
                }
            })
            ->first();
    }

    /**
     * The callback token already proved which gateway this is. A shared
     * provider message id, or a uuid from another gateway, must not match.
     *
     * @return Builder<SmsMessage>
     */
    private function forProvider(string $providerKey): Builder
    {
        return SmsMessage::query()->where(function (Builder $query) use ($providerKey): void {
            $query->whereHas('provider', fn (Builder $provider) => $provider->where('key', $providerKey))
                ->orWhereHas('events', fn (Builder $events) => $events
                    ->where('provider_key', $providerKey)
                    ->where('type', 'result'));
        });
    }
}
