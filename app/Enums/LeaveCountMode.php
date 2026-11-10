<?php

namespace App\Enums;

enum LeaveCountMode: string
{
    case Calendar = 'calendar';
    case Working = 'working';

    public function label(): string
    {
        return match ($this) {
            self::Calendar => 'Every calendar day',
            self::Working => 'Working days only',
        };
    }
}
