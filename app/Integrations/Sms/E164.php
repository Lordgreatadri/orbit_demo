<?php

namespace App\Integrations\Sms;

use App\Integrations\Sms\Exceptions\InvalidPhoneNumber;

final class E164
{
    public static function assert(string $phone): string
    {
        $normalized = preg_replace('/[\s\-()]/', '', trim($phone)) ?? '';

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $normalized)) {
            throw new InvalidPhoneNumber('Enter the phone number in international format, starting with +.');
        }

        return $normalized;
    }

    public static function digits(string $phone): string
    {
        return ltrim(preg_replace('/\D+/', '', $phone) ?? '', '0');
    }
}
