<?php

namespace App\Jobs\Sms;

use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\DeliveryOutcome;
use App\Integrations\Sms\Exceptions\PermanentSmsException;
use App\Integrations\Sms\Exceptions\RetryableSmsException;
use App\Integrations\Sms\SmsLog;
use App\Integrations\Sms\SmsProviderRegistry;
use App\Integrations\Sms\SmsText;
use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use App\Models\SmsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class DeliverSmsMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;

    /**
     * @param  list<int>  $messageIds
     */
    public function __construct(public array $messageIds) {}

    public function handle(SmsProviderRegistry $registry): void
    {
        $messages = SmsMessage::query()
            ->whereIn('id', $this->messageIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (SmsMessage $message) => $message->status->isDispatchable())
            ->values();

        if ($messages->isEmpty()) {
            return;
        }

        $released = false;

        foreach ($messages->groupBy('sms_provider_id') as $providerId => $group) {
            $provider = SmsProvider::query()->find($providerId);

            if ($provider === null || ! $provider->enabled) {
                foreach ($group as $message) {
                    $message->markFailed('The provider is no longer available.');
                }

                continue;
            }

            if ($this->overRateLimit($provider, $group->count())) {
                $this->release(max(5, RateLimiter::availableIn('sms-provider:'.$provider->key)));
                $released = true;

                continue;
            }

            foreach ($group as $message) {
                $message->markSubmitted();
            }

            $outcomes = $this->deliver($registry, $provider, $group->all());
            $retryDelay = $this->apply($registry, $provider, $group, $outcomes);

            if ($retryDelay !== null) {
                // The sync driver records a release and does not run the job again.
                // Database and Redis queues honour the delay, which keeps the worker free.
                if ($this->job instanceof SyncJob) {
                    $this->handle($registry);
                } else {
                    $this->release($retryDelay);
                }

                return;
            }

            RefreshSmsProviderBalance::dispatch($provider->id);
        }

        if ($released) {
            return;
        }
    }

    public function failed(?Throwable $exception): void
    {
        SmsMessage::query()
            ->whereIn('id', $this->messageIds)
            ->get()
            ->each(function (SmsMessage $message) use ($exception): void {
                if ($message->status->isDispatchable()) {
                    $message->markFailed(SmsText::safe($exception?->getMessage() ?: 'The message could not be sent.'));
                }
            });
    }

    /**
     * @param  list<SmsMessage>  $messages
     * @return list<DeliveryOutcome>
     */
    private function deliver(SmsProviderRegistry $registry, SmsProvider $provider, array $messages): array
    {
        try {
            return $registry->get($provider->key)->deliver($provider, $messages);
        } catch (RetryableSmsException $exception) {
            return array_map(
                fn (SmsMessage $message) => new DeliveryOutcome($message->id, 'retry', error: $exception->getMessage()),
                $messages,
            );
        } catch (PermanentSmsException $exception) {
            return array_map(
                fn (SmsMessage $message) => new DeliveryOutcome($message->id, 'failed', error: $exception->getMessage()),
                $messages,
            );
        }
    }

    /**
     * @param  Collection<int, SmsMessage>  $group
     * @param  list<DeliveryOutcome>  $outcomes
     */
    private function apply(SmsProviderRegistry $registry, SmsProvider $provider, Collection $group, array $outcomes): ?int
    {
        $byId = $group->keyBy('id');
        $retryDelay = null;
        $fallbacks = [];

        foreach ($outcomes as $outcome) {
            $message = $byId->get($outcome->localId);

            if ($message === null) {
                continue;
            }

            $this->recordResult($message, $provider, $outcome);

            if ($outcome->result === 'accepted') {
                $message->markAccepted($outcome->providerMessageId, $outcome->providerStatus, $provider->id);

                continue;
            }

            if ($outcome->result === 'delivered') {
                $message->markAccepted($outcome->providerMessageId, $outcome->providerStatus, $provider->id);
                $message->markDelivered($outcome->providerStatus);

                continue;
            }

            if ($outcome->result === 'retry') {
                $message->recordAttempt($outcome->error ?: 'The provider did not accept the message.');

                if ($message->attempts >= $message->max_attempts) {
                    $fallback = $this->claimFallback($message);

                    if ($fallback !== null) {
                        $fallbacks[$fallback->id][] = $message->fresh();
                    } else {
                        $message->markFailed($outcome->error ?: 'The provider did not accept the message.', $outcome->providerStatus);
                    }

                    continue;
                }

                $retryDelay = max($retryDelay ?? 0, $message->nextBackoff());

                continue;
            }

            $fallback = $this->claimFallback($message);

            if ($fallback !== null) {
                $fallbacks[$fallback->id][] = $message->fresh();

                continue;
            }

            $message->markFailed($outcome->error ?: 'The provider rejected the message.', $outcome->providerStatus);
        }

        foreach ($fallbacks as $fallbackId => $messages) {
            $fallback = SmsProvider::query()->find($fallbackId);

            if ($fallback === null) {
                foreach ($messages as $message) {
                    $message->markFailed('The fallback provider is no longer available.');
                }

                continue;
            }

            $fallbackOutcomes = $this->deliver($registry, $fallback, $messages);

            foreach ($fallbackOutcomes as $outcome) {
                $message = collect($messages)->firstWhere('id', $outcome->localId);

                if ($message === null) {
                    continue;
                }

                $this->recordResult($message, $fallback, $outcome);

                if (in_array($outcome->result, ['accepted', 'delivered'], true)) {
                    $message->markAccepted($outcome->providerMessageId, $outcome->providerStatus, $fallback->id);

                    if ($outcome->result === 'delivered') {
                        $message->markDelivered($outcome->providerStatus);
                    }

                    RefreshSmsProviderBalance::dispatch($fallback->id);

                    continue;
                }

                $message->markFailed($outcome->error ?: 'The fallback provider rejected the message.', $outcome->providerStatus);
            }
        }

        return $retryDelay;
    }

    private function claimFallback(SmsMessage $message): ?SmsProvider
    {
        if (! $message->allow_fallback || $message->used_fallback) {
            return null;
        }

        $fallback = SmsProvider::query()
            ->where('is_fallback', true)
            ->where('enabled', true)
            ->where('id', '!=', $message->sms_provider_id)
            ->first();

        if ($fallback === null) {
            return null;
        }

        $message->used_fallback = true;
        $message->sms_provider_id = $fallback->id;
        $message->sender_id = $fallback->sender_id;
        $message->attempts = 0;
        $message->max_attempts = $fallback->max_attempts;
        $message->backoff_seconds = $fallback->backoffSchedule();
        $message->status = SmsMessageStatus::Submitted;
        $message->save();

        return $fallback;
    }

    private function recordResult(SmsMessage $message, SmsProvider $provider, DeliveryOutcome $outcome): void
    {
        SmsMessageEvent::query()->create([
            'sms_message_id' => $message->id,
            'sms_provider_id' => $provider->id,
            'provider_key' => $provider->key,
            'type' => 'result',
            'status' => $outcome->result,
            'provider_status' => SmsMessage::shortStatus($outcome->providerStatus),
            'payload' => array_filter([
                'result' => $outcome->result,
                'error' => $outcome->error !== null ? SmsText::safe($outcome->error) : null,
            ]),
        ]);

        SmsLog::provider($provider->key)->info('SMS provider responded.', [
            'provider' => $provider->key,
            'message' => $message->uuid,
            'to' => $message->maskedTo(),
            'result' => $outcome->result,
            'provider_status' => SmsMessage::shortStatus($outcome->providerStatus),
            'error' => $outcome->error !== null ? SmsText::safe($outcome->error) : null,
        ]);
    }

    private function overRateLimit(SmsProvider $provider, int $slots): bool
    {
        $key = 'sms-provider:'.$provider->key;
        $limit = max(1, (int) $provider->requests_per_minute);

        if (RateLimiter::attempts($key) + $slots > $limit) {
            return true;
        }

        RateLimiter::increment($key, 60, $slots);

        return false;
    }
}
