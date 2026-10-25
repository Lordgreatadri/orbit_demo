<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending verification',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Closed => 'Closed',
        };
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::PendingVerification, self::Active], true);
    }
}
