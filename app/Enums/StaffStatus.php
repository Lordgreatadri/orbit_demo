<?php

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Inactive = 'inactive';
    case Exited = 'exited';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Inactive => 'Inactive',
            self::Exited => 'Exited',
        };
    }

    /** Badge tone used by the UI (labels are always shown alongside colour). */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnLeave => 'accent',
            self::Inactive => 'warning',
            self::Exited => 'neutral',
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
