<?php

namespace App\Jobs\Sms;

use App\Integrations\Sms\Webhooks\SmsCallbackApplier;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessWigalFrogWebhook extends ProcessWebhookJob
{
    public function handle(SmsCallbackApplier $applier): void
    {
        $applier->apply('wigal', $this->webhookCall->payload ?? []);
    }
}
