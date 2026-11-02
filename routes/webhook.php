<?php

use App\Integrations\Sms\SmsProviderRegistry;
use Illuminate\Support\Facades\Route;

/*
 * Provider delivery reports. These routes sit outside the session stack, so
 * they do not use the CSRF cookie. Each provider has its own signature check
 * and queued job. Every job writes the same sms_messages table.
 */

foreach (app(SmsProviderRegistry::class)->all() as $adapter) {
    Route::webhooks(
        'webhooks/sms/'.$adapter->key().'/{token}',
        $adapter->webhookName(),
        $adapter->webhookMethods(),
    )->middleware('throttle:sms-webhook')->where('token', '[A-Za-z0-9]{32,128}');
}
