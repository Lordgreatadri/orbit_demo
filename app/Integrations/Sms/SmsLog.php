<?php

namespace App\Integrations\Sms;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Provider traffic goes to its own daily file. The shared laravel.log stays
 * for everything that is not an SMS gateway or a sign-in.
 */
final class SmsLog
{
    public static function provider(string $key): LoggerInterface
    {
        $channel = 'sms-'.$key;

        if (! array_key_exists($channel, config('logging.channels', []))) {
            $channel = 'sms';
        }

        return Log::channel($channel);
    }
}
