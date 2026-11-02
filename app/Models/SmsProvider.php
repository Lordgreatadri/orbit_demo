<?php

namespace App\Models;

use App\Enums\SmsProviderHealth;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SmsProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'enabled',
    'is_active',
    'is_fallback',
    'sender_id',
    'credentials',
    'timeout_seconds',
    'max_attempts',
    'backoff_seconds',
    'requests_per_minute',
    'low_balance_threshold',
    'low_bundle_threshold',
    'balance',
    'sms_bundle_balance',
    'balance_unit',
    'cash_balance',
    'cash_balance_unit',
    'balance_checked_at',
    'low_balance_notified_at',
    'health_status',
    'health_checked_at',
    'health_message',
    'sandbox',
])]
#[Hidden(['credentials'])]
class SmsProvider extends Model
{
    /** @use HasFactory<SmsProviderFactory> */
    use HasFactory, HasPublicUuid;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_fallback' => 'boolean',
            'sandbox' => 'boolean',
            'credentials' => 'encrypted:array',
            'backoff_seconds' => 'array',
            'low_balance_threshold' => 'decimal:2',
            'low_bundle_threshold' => 'integer',
            'balance' => 'decimal:2',
            'sms_bundle_balance' => 'integer',
            'cash_balance' => 'decimal:2',
            'balance_checked_at' => 'datetime',
            'low_balance_notified_at' => 'datetime',
            'health_checked_at' => 'datetime',
            'health_status' => SmsProviderHealth::class,
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        $credentials = $this->credentials ?? [];

        return $credentials[$key] ?? $default;
    }

    public function hasCredential(string $key): bool
    {
        $value = $this->credential($key);

        return is_string($value) && $value !== '';
    }

    public function masked(string $key): ?string
    {
        $value = $this->credential($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        $tail = mb_substr($value, -4);

        return '••••'.$tail;
    }

    public function webhookUrl(): string
    {
        $token = (string) $this->credential('webhook_token');

        return route('webhook-client-'.$this->key, ['token' => $token]);
    }

    /**
     * SMS credits from the last check. Older rows stored the bundle in balance
     * before sms_bundle_balance existed.
     */
    public function smsBundleReading(): ?string
    {
        if ($this->sms_bundle_balance !== null) {
            return (string) $this->sms_bundle_balance;
        }

        if ($this->balance_unit === 'SMS' && $this->balance !== null) {
            return (string) (int) round((float) $this->balance);
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function backoffSchedule(): array
    {
        $values = array_values(array_filter(
            array_map('intval', $this->backoff_seconds ?? []),
            fn (int $seconds) => $seconds > 0,
        ));

        return $values === [] ? [30, 120, 300] : $values;
    }

    public function backoffForAttempt(int $attempt): int
    {
        $schedule = $this->backoffSchedule();
        $index = max(0, $attempt - 1);

        return $schedule[min($index, count($schedule) - 1)];
    }
}
