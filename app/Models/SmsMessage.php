<?php

namespace App\Models;

use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\SmsText;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'organization_id',
    'sms_provider_id',
    'idempotency_key',
    'to',
    'body',
    'sender_id',
    'purpose',
    'status',
    'provider_message_id',
    'provider_status',
    'attempts',
    'max_attempts',
    'backoff_seconds',
    'allow_fallback',
    'used_fallback',
    'counts_toward_subscription',
    'billing_scope',
    'last_error',
    'queued_at',
    'submitted_at',
    'accepted_at',
    'delivered_at',
    'failed_at',
])]
class SmsMessage extends Model
{
    use HasPublicUuid;

    protected function casts(): array
    {
        return [
            'status' => SmsMessageStatus::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'backoff_seconds' => 'array',
            'allow_fallback' => 'boolean',
            'used_fallback' => 'boolean',
            'counts_toward_subscription' => 'boolean',
            'queued_at' => 'datetime',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SmsProvider::class, 'sms_provider_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SmsMessageEvent::class);
    }

    public function maskedTo(): string
    {
        $digits = preg_replace('/\D+/', '', $this->to) ?? '';

        return '••••'.mb_substr($digits, -4);
    }

    public function markSubmitted(): void
    {
        $this->withinLock(function (self $row): void {
            if ($row->status->isTerminal()) {
                return;
            }

            if ($row->status->canAdvanceTo(SmsMessageStatus::Submitted)) {
                $row->status = SmsMessageStatus::Submitted;
            }

            $row->submitted_at ??= now();
            $row->save();
        });
    }

    public function markAccepted(?string $providerMessageId, ?string $providerStatus, ?int $providerId = null): void
    {
        $this->withinLock(function (self $row) use ($providerMessageId, $providerStatus, $providerId): void {
            if ($row->status->isTerminal()) {
                if ($providerMessageId && $row->provider_message_id === null) {
                    $row->provider_message_id = mb_substr($providerMessageId, 0, 120);
                    $row->save();
                }

                return;
            }

            if ($providerId !== null) {
                $row->sms_provider_id = $providerId;
            }

            if ($providerMessageId) {
                $row->provider_message_id = mb_substr($providerMessageId, 0, 120);
            }

            $row->provider_status = self::shortStatus($providerStatus);
            $row->last_error = null;

            if ($row->status->canAdvanceTo(SmsMessageStatus::Accepted)) {
                $row->status = SmsMessageStatus::Accepted;
                $row->accepted_at = now();
            }

            $row->save();
        });
    }

    public function markDelivered(?string $providerStatus): void
    {
        $this->withinLock(function (self $row) use ($providerStatus): void {
            if ($row->status === SmsMessageStatus::Failed || $row->status === SmsMessageStatus::Logged) {
                return;
            }

            $row->provider_status = self::shortStatus($providerStatus);

            if ($row->status->canAdvanceTo(SmsMessageStatus::Delivered)) {
                $row->status = SmsMessageStatus::Delivered;
                $row->delivered_at = now();
                $row->last_error = null;
            }

            $row->save();
        });
    }

    public function markFailed(string $error, ?string $providerStatus = null): void
    {
        $this->withinLock(function (self $row) use ($error, $providerStatus): void {
            if (! $row->status->canAdvanceTo(SmsMessageStatus::Failed)) {
                return;
            }

            $row->provider_status = self::shortStatus($providerStatus);
            $row->last_error = SmsText::safe($error);
            $row->status = SmsMessageStatus::Failed;
            $row->failed_at = now();
            $row->save();
        });
    }

    public function markLogged(): void
    {
        $this->withinLock(function (self $row): void {
            if (! $row->status->canAdvanceTo(SmsMessageStatus::Logged)) {
                return;
            }

            $row->status = SmsMessageStatus::Logged;
            $row->save();
        });
    }

    public function recordAttempt(string $error): void
    {
        $this->withinLock(function (self $row) use ($error): void {
            if ($row->status->isTerminal()) {
                return;
            }

            $row->attempts++;
            $row->last_error = SmsText::safe($error);
            $row->submitted_at ??= now();
            $row->save();
        });
    }

    /**
     * Move the message onto the fallback provider unless a callback already finished it.
     */
    public function assignFallback(SmsProvider $fallback): bool
    {
        if (! $this->allow_fallback || $this->used_fallback) {
            return false;
        }

        $assigned = false;

        $this->withinLock(function (self $row) use ($fallback, &$assigned): void {
            if ($row->status->isTerminal() || $row->used_fallback || ! $row->allow_fallback) {
                return;
            }

            $row->used_fallback = true;
            $row->sms_provider_id = $fallback->id;
            $row->sender_id = $fallback->sender_id;
            $row->attempts = 0;
            $row->max_attempts = $fallback->max_attempts;
            $row->backoff_seconds = $fallback->backoffSchedule();
            $row->status = SmsMessageStatus::Submitted;
            $row->save();
            $assigned = true;
        });

        return $assigned;
    }

    /**
     * Re-read the row under a lock so a delivery report cannot be overwritten
     * by a send result that still has the older status in memory.
     */
    public function withinLock(callable $callback): void
    {
        DB::transaction(function () use ($callback): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return;
            }

            $callback($locked);

            $this->setRawAttributes($locked->getAttributes(), true);
            $this->syncOriginal();
        });
    }

    public function nextBackoff(): int
    {
        $schedule = array_values(array_filter(
            array_map('intval', $this->backoff_seconds ?? []),
            fn (int $seconds) => $seconds > 0,
        ));

        if ($schedule === []) {
            $schedule = [30, 120, 300];
        }

        $index = max(0, $this->attempts - 1);

        return $schedule[min($index, count($schedule) - 1)];
    }

    public static function shortStatus(?string $status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $clean = preg_replace('/[^A-Za-z0-9_\- ]/', '', $status) ?? '';

        return mb_substr(trim($clean), 0, 40) ?: null;
    }
}
