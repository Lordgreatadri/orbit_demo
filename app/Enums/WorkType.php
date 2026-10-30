<?php

namespace App\Enums;

enum WorkType: string
{
    case Office = 'office';
    case Field = 'field';
    case Remote = 'remote';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Office',
            self::Field => 'Field',
            self::Remote => 'Remote',
            self::Hybrid => 'Hybrid',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
