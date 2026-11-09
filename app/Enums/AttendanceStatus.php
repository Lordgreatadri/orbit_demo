<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case PendingVerification = 'pending_verification';
    case Rejected = 'rejected';
    case Incomplete = 'incomplete';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::OnLeave => 'On leave',
            self::PendingVerification => 'Pending verification',
            self::Rejected => 'Rejected',
            self::Incomplete => 'Incomplete',
            self::Excused => 'Excused',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Present, self::Excused => 'success',
            self::Late, self::Incomplete, self::PendingVerification => 'warning',
            self::Absent, self::Rejected => 'danger',
            self::OnLeave => 'accent',
        };
    }

    public function blocksCheckIn(): bool
    {
        return match ($this) {
            self::Present, self::Late, self::Incomplete, self::Excused => true,
            default => false,
        };
    }
}
