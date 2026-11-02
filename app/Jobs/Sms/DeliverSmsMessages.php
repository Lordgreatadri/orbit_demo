<?php

namespace App\Jobs\Sms;

use App\Contracts\Sms\SmsProviderAdapter;
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
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class DeliverSmsMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 15;

    /** @var array<int, Lock> */
    private array $claims = [];

    private bool $deferForRateLimit = false;

    private ?int $limitDelay = null;

    /**
     * @param  list<int>  $messageIds
     */
    public function __construct(public array $messageIds) {}

    public static function claimKey(int $messageId): string
    {
        return 'sms-deliver:'.$messageId;
    }

    public function handle(SmsProviderRegistry $registry): void
    {
        $this->deferForRateLimit = false;
        $this->limitDelay = null;

        $messages = SmsMessage::query()
            ->whereIn('id', $this->messageIds)
            ->orderBy('id')
            ->get()
            ->filter(fn (SmsMessage $message) => $message->status->isDispatchable())
            ->values();

        if ($messages->isEmpty()) {
            return;
        }

        $claimed = $this->claim($messages);

        if ($claimed->isEmpty()) {
            return;
        }

        try {
            $retryDelay = null;

            foreach ($claimed->groupBy('sms_provider_id') as $providerId => $group) {
                $provider = SmsProvider::query()->find($providerId);

                if ($provider === null || ! $provider->enabled) {
                    foreach ($group as $message) {
                        $message->markFailed('The provider is no longer available.');
                    }

                    continue;
                }

                $chunk = $this->reserveChunk($provider, $group->all());

                if ($chunk === []) {
                    continue;
                }

                foreach ($chunk as $message) {
                    $message->markSubmitted();
                }

                $outcomes = $this->deliver($registry, $provider, $chunk);
                $delay = $this->apply($registry, $provider, collect($chunk), $outcomes);

                if ($delay !== null) {
                    $retryDelay = max($retryDelay ?? 0, $delay);
                } else {
                    RefreshSmsProviderBalance::dispatch($provider->id);
                }
            }

            if ($retryDelay !== null || ($this->deferForRateLimit && $this->queueCanDelay())) {
                $delay = max($retryDelay ?? 0, $this->limitDelay ?? 5);
                $this->releaseClaims();

                if ($this->queueCanDelay()) {
                    $this->release($delay);
                } elseif ($retryDelay !== null) {
                    // The sync driver records a release and does not run the job again.
                    $this->handle($registry);
                }
            }
        } finally {
            $this->releaseClaims();
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
            // Adapters catch a timeout around each request and keep outcomes they already have.
            // This remains for a call that fails before it can return any of those outcomes.
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

            $chunk = $this->reserveChunk($fallback, array_values($messages));

            if ($chunk === []) {
                continue;
            }

            $fallbackOutcomes = $this->deliver($registry, $fallback, $chunk);

            foreach ($fallbackOutcomes as $outcome) {
                $message = collect($chunk)->firstWhere('id', $outcome->localId);

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

        if ($fallback === null || ! $message->assignFallback($fallback)) {
            return null;
        }

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

    /**
     * @param  Collection<int, SmsMessage>  $messages
     * @return Collection<int, SmsMessage>
     */
    private function claim(Collection $messages): Collection
    {
        $claimed = collect();

        foreach ($messages as $message) {
            $lock = Cache::lock(self::claimKey($message->id), 600);

            if (! $lock->get()) {
                continue;
            }

            $fresh = SmsMessage::query()->find($message->id);

            if ($fresh === null || ! $fresh->status->isDispatchable()) {
                $lock->release();

                continue;
            }

            $this->claims[$fresh->id] = $lock;
            $claimed->push($fresh);
        }

        return $claimed;
    }

    private function releaseClaims(): void
    {
        foreach ($this->claims as $lock) {
            $lock->release();
        }

        $this->claims = [];
    }

    /**
     * Take only as many messages as this minute still allows, counted as provider HTTP calls.
     * A later chunk is left for another run. The sync driver cannot schedule that run, so those
     * messages are failed instead of staying queued forever.
     *
     * @param  list<SmsMessage>  $messages
     * @return list<SmsMessage>
     */
    private function reserveChunk(SmsProvider $provider, array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $adapter = app(SmsProviderRegistry::class)->get($provider->key);
        $limit = max(1, (int) $provider->requests_per_minute);
        $room = $limit - RateLimiter::attempts($this->rateKey($provider));

        if ($room < 1) {
            $this->holdForLimit($provider, $messages);

            return [];
        }

        [$chunk, $rest] = $this->splitToFit($adapter, $messages, $room);

        if ($chunk === []) {
            $this->holdForLimit($provider, $messages);

            return [];
        }

        RateLimiter::increment($this->rateKey($provider), 60, $adapter->transmissionRequests($chunk));

        if ($rest !== []) {
            $this->holdForLimit($provider, $rest);
        }

        return $chunk;
    }

    /**
     * @param  list<SmsMessage>  $messages
     * @return array{0: list<SmsMessage>, 1: list<SmsMessage>}
     */
    private function splitToFit(SmsProviderAdapter $adapter, array $messages, int $room): array
    {
        $take = [];

        foreach ($messages as $offset => $message) {
            $candidate = [...$take, $message];

            if ($adapter->transmissionRequests($candidate) > $room) {
                return [$take, array_slice($messages, $offset)];
            }

            $take[] = $message;
        }

        return [$take, []];
    }

    /**
     * @param  list<SmsMessage>  $messages
     */
    private function holdForLimit(SmsProvider $provider, array $messages): void
    {
        if ($this->queueCanDelay()) {
            $this->deferForRateLimit = true;
            $this->limitDelay = max($this->limitDelay ?? 0, max(5, RateLimiter::availableIn($this->rateKey($provider))));

            return;
        }

        foreach ($messages as $message) {
            $message->markFailed('The provider rate limit was reached and this queue cannot try again later.');
        }
    }

    private function queueCanDelay(): bool
    {
        return $this->job !== null && ! $this->job instanceof SyncJob;
    }

    private function rateKey(SmsProvider $provider): string
    {
        return 'sms-provider:'.$provider->key;
    }
}
