<?php

namespace App\Enums;

enum LocationType: string
{
    case Office = 'office';
    case FieldArea = 'field_area';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::Office => 'Office',
            self::FieldArea => 'Field area',
            self::Remote => 'Remote',
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
