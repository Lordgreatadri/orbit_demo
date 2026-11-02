<?php

namespace App\Enums;

enum SmsMessageStatus: string
{
    case Queued = 'queued';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Logged = 'logged';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Submitted => 'Submitted',
            self::Accepted => 'Accepted',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Logged => 'Logged',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Queued, self::Submitted => 'info',
            self::Accepted => 'warning',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Logged => 'neutral',
        };
    }

    public function isDispatchable(): bool
    {
        return in_array($this, [self::Queued, self::Submitted], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Failed, self::Logged], true);
    }

    /**
     * Provider acceptance is not delivery. Delivered is only reached from a
     * later status report, and a later report must not walk a message backwards.
     */
    public function canAdvanceTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return match ($this) {
            self::Queued => in_array($next, [self::Submitted, self::Accepted, self::Delivered, self::Failed, self::Logged], true),
            self::Submitted => in_array($next, [self::Accepted, self::Delivered, self::Failed], true),
            self::Accepted => in_array($next, [self::Delivered, self::Failed], true),
            self::Delivered, self::Failed, self::Logged => false,
        };
    }
}
