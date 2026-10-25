<?php

use Illuminate\Support\Facades\Route;

/*
 * Provider delivery reports. These routes are outside the session stack, so
 * they do not accept a CSRF cookie. Each named config in config/webhook-client.php
 * checks the signature before anything is stored.
 */

// Route::webhooks('webhooks/sms/frog', 'frog-sms');
// Route::webhooks('webhooks/sms/twilio', 'twilio-sms');
// ...
