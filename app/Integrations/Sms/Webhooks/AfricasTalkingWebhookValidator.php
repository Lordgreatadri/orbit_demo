<?php

namespace App\Integrations\Sms\Webhooks;

class AfricasTalkingWebhookValidator extends WebhookPathTokenValidator
{
    protected function providerKey(): string
    {
        return 'africastalking';
    }
}
