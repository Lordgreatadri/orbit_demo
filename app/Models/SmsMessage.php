<?php

namespace App\Models;

use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\SmsText;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        if ($this->status->canAdvanceTo(SmsMessageStatus::Submitted)) {
            $this->status = SmsMessageStatus::Submitted;
        }

        $this->submitted_at ??= now();
        $this->save();
    }

    public function markAccepted(?string $providerMessageId, ?string $providerStatus, ?int $providerId = null): void
    {
        if ($providerId !== null) {
            $this->sms_provider_id = $providerId;
        }

        if ($providerMessageId) {
            $this->provider_message_id = mb_substr($providerMessageId, 0, 120);
        }

        $this->provider_status = self::shortStatus($providerStatus);
        $this->last_error = null;

        if ($this->status->canAdvanceTo(SmsMessageStatus::Accepted)) {
            $this->status = SmsMessageStatus::Accepted;
            $this->accepted_at = now();
        }

        $this->save();
    }

    public function markDelivered(?string $providerStatus): void
    {
        $this->provider_status = self::shortStatus($providerStatus);

        if ($this->status->canAdvanceTo(SmsMessageStatus::Delivered)) {
            $this->status = SmsMessageStatus::Delivered;
            $this->delivered_at = now();
            $this->last_error = null;
        }

        $this->save();
    }

    public function markFailed(string $error, ?string $providerStatus = null): void
    {
        $this->provider_status = self::shortStatus($providerStatus);
        $this->last_error = SmsText::safe($error);

        if ($this->status->canAdvanceTo(SmsMessageStatus::Failed)) {
            $this->status = SmsMessageStatus::Failed;
            $this->failed_at = now();
        }

        $this->save();
    }

    public function markLogged(): void
    {
        if ($this->status->canAdvanceTo(SmsMessageStatus::Logged)) {
            $this->status = SmsMessageStatus::Logged;
        }

        $this->save();
    }

    public function recordAttempt(string $error): void
    {
        $this->attempts++;
        $this->last_error = SmsText::safe($error);
        $this->submitted_at ??= now();
        $this->save();
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
