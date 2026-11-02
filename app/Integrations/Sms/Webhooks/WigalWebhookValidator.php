<?php

namespace App\Integrations\Sms\Webhooks;

class WigalWebhookValidator extends WebhookPathTokenValidator
{
    protected function providerKey(): string
    {
        return 'wigal';
    }
}
