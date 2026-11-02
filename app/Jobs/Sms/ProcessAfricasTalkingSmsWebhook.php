<?php

namespace App\Jobs\Sms;

use App\Integrations\Sms\Webhooks\SmsCallbackApplier;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessAfricasTalkingSmsWebhook extends ProcessWebhookJob
{
    public function handle(SmsCallbackApplier $applier): void
    {
        $applier->apply('africastalking', $this->webhookCall->payload ?? []);
    }
}
