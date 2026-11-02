<?php

use App\Integrations\Sms\SmsProviderRegistry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sms:sync-providers', function () {
    $count = app(SmsProviderRegistry::class)->ensureRows();
    $this->info('SMS providers synced ('.$count.' registered). Existing settings were left in place.');
})->purpose('Create configuration rows for registered SMS adapters');
