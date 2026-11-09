<?php

namespace App\Enums;

enum FieldActivityStatus: string
{
    case Active = 'active';
    case Delayed = 'delayed';
    case Completed = 'completed';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Delayed => 'Delayed',
            self::Completed => 'Completed',
            self::Offline => 'Offline',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Delayed => 'warning',
            self::Completed => 'neutral',
            self::Offline => 'danger',
        };
    }
}
