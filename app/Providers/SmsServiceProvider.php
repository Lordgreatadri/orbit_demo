<?php

namespace App\Providers;

use App\Integrations\Sms\SmsProviderRegistry;
use App\Integrations\Sms\SmsTransmitter;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsProviderRegistry::class);
        $this->app->singleton(SmsTransmitter::class);
    }

    public function boot(): void
    {
        config([
            'webhook-client.configs' => $this->app->make(SmsProviderRegistry::class)->webhookClientConfigs(),
            'webhook-client.delete_after_days' => 30,
            'webhook-client.add_unique_token_to_route_name' => false,
        ]);
    }
}
