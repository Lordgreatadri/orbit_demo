<?php

namespace App\Workforce\Communication;

use App\Integrations\Sms\Exceptions\InvalidPhoneNumber;
use App\Integrations\Sms\Exceptions\PermanentSmsException;
use App\Integrations\Sms\Exceptions\RetryableSmsException;
use InvalidArgumentException;
use Throwable;

/**
 * Normalises a provider failure so the workforce job can retry a temporary
 * outage and stop on a permanent recipient or configuration error.
 */
final class DeliveryFailure
{
    public static function kind(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof InvalidPhoneNumber, $exception instanceof PermanentSmsException => 'permanent',
            $exception instanceof RetryableSmsException => 'temporary',
            $exception instanceof InvalidArgumentException => 'invalid_configuration',
            str_contains($exception::class, 'RateLimit') => 'rate_limited',
            default => 'unknown',
        };
    }

    public static function shouldRetry(Throwable $exception): bool
    {
        return in_array(self::kind($exception), ['temporary', 'rate_limited', 'unknown'], true);
    }
}
