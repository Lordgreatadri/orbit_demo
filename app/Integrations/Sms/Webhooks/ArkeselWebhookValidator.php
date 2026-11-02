<?php

namespace App\Integrations\Sms\Webhooks;

class ArkeselWebhookValidator extends WebhookPathTokenValidator
{
    protected function providerKey(): string
    {
        return 'arkesel';
    }
}
