<?php

namespace App\Enums;

enum FieldAssignmentStatus: string
{
    case Assigned = 'assigned';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Assigned => 'info',
            self::Active => 'success',
            self::Completed => 'neutral',
            self::Cancelled => 'danger',
        };
    }
}
