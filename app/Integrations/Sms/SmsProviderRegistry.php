<?php

namespace App\Integrations\Sms;

use App\Contracts\Sms\SmsProviderAdapter;
use App\Enums\SmsProviderHealth;
use App\Integrations\Sms\Providers\AfricasTalkingAdapter;
use App\Integrations\Sms\Providers\ArkeselAdapter;
use App\Integrations\Sms\Providers\TwilioAdapter;
use App\Integrations\Sms\Providers\WigalFrogAdapter;
use App\Integrations\Sms\Webhooks\SmsWebhookCall;
use App\Models\SmsProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

/**
 * The only place a provider is selected. Add an adapter by appending its class below.
 */
final class SmsProviderRegistry
{
    /** @var array<string, SmsProviderAdapter> */
    private array $adapters;

    public function __construct()
    {
        $this->adapters = [];

        foreach ([
            new WigalFrogAdapter,
            new TwilioAdapter,
            new AfricasTalkingAdapter,
            new ArkeselAdapter,
        ] as $adapter) {
            $this->adapters[$adapter->key()] = $adapter;
        }
    }

    public function get(string $key): SmsProviderAdapter
    {
        if (! isset($this->adapters[$key])) {
            throw new InvalidArgumentException('Unknown SMS provider ['.$key.'].');
        }

        return $this->adapters[$key];
    }

    /**
     * @return list<SmsProviderAdapter>
     */
    public function all(): array
    {
        return array_values($this->adapters);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function webhookClientConfigs(): array
    {
        return array_map(fn (SmsProviderAdapter $adapter) => [
            'name' => $adapter->webhookName(),
            'signing_secret' => '',
            'signature_header_name' => 'Signature',
            'signature_validator' => $adapter->signatureValidator(),
            'webhook_profile' => ProcessEverythingWebhookProfile::class,
            'webhook_response' => DefaultRespondsTo::class,
            'webhook_model' => SmsWebhookCall::class,
            'store_headers' => [],
            'store_attachments' => false,
            'process_webhook_job' => $adapter->processWebhookJob(),
        ], $this->all());
    }

    /**
     * Insert a row for each registered adapter. Existing settings, history, and tokens are left alone.
     */
    public function ensureRows(): int
    {
        foreach ($this->all() as $adapter) {
            $provider = SmsProvider::query()->firstOrCreate(
                ['key' => $adapter->key()],
                [
                    'name' => $adapter->name(),
                    'timeout_seconds' => 10,
                    'max_attempts' => 3,
                    'backoff_seconds' => [30, 120, 300],
                    'requests_per_minute' => $adapter->defaultRequestsPerMinute(),
                    'credentials' => ['webhook_token' => Str::random(48)],
                    'health_status' => SmsProviderHealth::Unknown,
                ],
            );

            if (! $provider->hasCredential('webhook_token')) {
                $credentials = $provider->credentials ?? [];
                $credentials['webhook_token'] = Str::random(48);
                $provider->credentials = $credentials;
                $provider->save();
            }
        }

        return count($this->adapters);
    }
}
