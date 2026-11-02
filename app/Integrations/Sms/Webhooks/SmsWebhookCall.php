<?php

namespace App\Integrations\Sms\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;

class SmsWebhookCall extends WebhookCall
{
    protected $table = 'webhook_calls';

    public static function storeWebhook(WebhookConfig $config, Request $request): WebhookCall
    {
        $token = (string) $request->route('token');
        $url = $request->fullUrl();

        if ($token !== '') {
            $url = str_replace($token, '[redacted]', $url);
        }

        $payload = $request->input();

        foreach (array_keys($payload) as $key) {
            if (preg_match('/token|secret|api[_-]?key|authorization|password/i', (string) $key)) {
                unset($payload[$key]);
            }
        }

        return static::create([
            'name' => $config->name,
            'url' => mb_substr($url, 0, 512),
            'headers' => [],
            'payload' => $payload,
            'exception' => null,
        ]);
    }
}
