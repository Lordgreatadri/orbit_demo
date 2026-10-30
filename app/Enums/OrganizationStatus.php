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

    /** Badge tone used by the UI. */
    public function tone(): string
    {
        return match ($this) {
            self::PendingVerification => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Closed => 'neutral',
        };
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::PendingVerification, self::Active], true);
    }
}
