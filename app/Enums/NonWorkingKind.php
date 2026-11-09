<?php

namespace App\Enums;

enum NonWorkingKind: string
{
    case Holiday = 'holiday';
    case Closure = 'closure';
    case NonWorking = 'non_working';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Holiday',
            self::Closure => 'Closure',
            self::NonWorking => 'Non-working day',
        };
    }
}
