<?php

namespace App\Enums;

enum SmsProviderHealth: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Not checked',
            self::Healthy => 'Healthy',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unknown => 'neutral',
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
        };
    }
}
