<?php

namespace App\Enums;

enum RoleScope: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::Tenant => 'Organisation',
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
