<?php

namespace App\Enums;

/**
 * How a work period expects attendance evidence.
 * The tenant names the arrangement. The kind is the contract with the attendance engine.
 */
enum WorkArrangementKind: string
{
    case Office = 'office';
    case Remote = 'remote';
    case Field = 'field';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Office',
            self::Remote => 'Remote',
            self::Field => 'Field',
            self::Flexible => 'Flexible',
        };
    }

    public function expectsSite(): bool
    {
        return $this === self::Office || $this === self::Field;
    }
}
