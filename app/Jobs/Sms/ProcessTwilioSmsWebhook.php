<?php

namespace App\Jobs\Sms;

use App\Integrations\Sms\Webhooks\SmsCallbackApplier;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessTwilioSmsWebhook extends ProcessWebhookJob
{
    public function handle(SmsCallbackApplier $applier): void
    {
        $applier->apply('twilio', $this->webhookCall->payload ?? []);
    }
}
