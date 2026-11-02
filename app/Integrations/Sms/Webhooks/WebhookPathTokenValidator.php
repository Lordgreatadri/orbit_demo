<?php

namespace App\Integrations\Sms\Webhooks;

use App\Models\SmsProvider;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

abstract class WebhookPathTokenValidator implements SignatureValidator
{
    abstract protected function providerKey(): string;

    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $given = (string) $request->route('token');
        $expected = SmsProvider::query()->where('key', $this->providerKey())->first()?->credential('webhook_token');

        if (! is_string($expected) || $expected === '' || $given === '') {
            return false;
        }

        return hash_equals($expected, $given);
    }
}
