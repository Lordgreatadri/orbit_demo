<?php

namespace App\Enums;

enum LoginEventType: string
{
    case Login = 'login';
    case Logout = 'logout';
    case Failed = 'failed';
    case Lockout = 'lockout';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Signed in',
            self::Logout => 'Signed out',
            self::Failed => 'Failed attempt',
            self::Lockout => 'Locked out',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Login => 'success',
            self::Logout => 'neutral',
            self::Failed => 'warning',
            self::Lockout => 'danger',
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
