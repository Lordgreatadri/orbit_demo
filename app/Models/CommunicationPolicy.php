<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_id',
    'email_enabled',
    'sms_enabled',
    'in_app_enabled',
    'leave_notifications_enabled',
    'schedule_notifications_enabled',
    'attendance_reminders_enabled',
    'announcements_enabled',
])]
class CommunicationPolicy extends Model
{
    use BelongsToOrganization;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'leave_notifications_enabled' => 'boolean',
            'schedule_notifications_enabled' => 'boolean',
            'attendance_reminders_enabled' => 'boolean',
            'announcements_enabled' => 'boolean',
        ];
    }

    /**
     * The saved policy, or an unsaved default that matches today's behaviour.
     * Reading the policy does not insert a row.
     */
    public static function forOrganization(Organization $organization): self
    {
        return static::withoutTenantScope()->firstOrNew(
            ['organization_id' => $organization->getKey()],
            [
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
                'leave_notifications_enabled' => true,
                'schedule_notifications_enabled' => true,
                'attendance_reminders_enabled' => true,
                'announcements_enabled' => true,
            ],
        );
    }

    public function allows(string $category, bool $reminder): bool
    {
        $categoryOn = match ($category) {
            'leave' => $this->leave_notifications_enabled,
            'schedule' => $this->schedule_notifications_enabled,
            'announcements' => $this->announcements_enabled,
            default => true,
        };

        if (! $categoryOn) {
            return false;
        }

        return ! $reminder || $this->attendance_reminders_enabled;
    }
}
