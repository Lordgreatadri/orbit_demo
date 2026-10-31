<?php

namespace App\Enums;

enum BrandThemeMode: string
{
    case User = 'user';
    case Light = 'light';
    case Plum = 'plum';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Let each person choose',
            self::Light => 'Light theme only',
            self::Plum => 'Deep teal theme only',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
