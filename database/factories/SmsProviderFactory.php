<?php

namespace Database\Factories;

use App\Enums\SmsProviderHealth;
use App\Models\SmsProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SmsProvider>
 */
class SmsProviderFactory extends Factory
{
    protected $model = SmsProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'wigal',
            'name' => 'Wigal Frog',
            'enabled' => false,
            'is_active' => false,
            'is_fallback' => false,
            'sender_id' => 'ORBIT',
            'credentials' => [
                'webhook_token' => Str::random(48),
                'username' => 'orbit-demo',
                'api_key' => 'test-api-key',
            ],
            'timeout_seconds' => 10,
            'max_attempts' => 3,
            'backoff_seconds' => [30, 120, 300],
            'requests_per_minute' => 30,
            'health_status' => SmsProviderHealth::Unknown,
            'sandbox' => false,
        ];
    }

    public function ready(string $key = 'wigal'): static
    {
        $credentials = match ($key) {
            'twilio' => [
                'webhook_token' => Str::random(48),
                'account_sid' => 'AC'.Str::repeat('a', 32),
                'auth_token' => 'twilio-auth-token-value',
            ],
            'arkesel' => [
                'webhook_token' => Str::random(48),
                'api_key' => 'arkesel-api-key-value',
            ],
            default => [
                'webhook_token' => Str::random(48),
                'username' => 'orbit-demo',
                'api_key' => 'test-api-key',
            ],
        };

        $names = [
            'wigal' => 'Wigal Frog',
            'twilio' => 'Twilio',
            'africastalking' => "Africa's Talking",
            'arkesel' => 'Arkesel',
        ];

        return $this->state([
            'key' => $key,
            'name' => $names[$key] ?? $key,
            'enabled' => true,
            'credentials' => $credentials,
            'sender_id' => $key === 'twilio' ? '+15550001111' : 'ORBIT',
        ]);
    }
}
