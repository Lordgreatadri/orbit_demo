<?php

namespace App\Workforce;

/**
 * Workforce notification events. Channels are chosen per organisation.
 * Push stays a plug. SMS uses the platform transmitter only when a provider is on.
 */
final class WorkforceEvents
{
    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return [
            'attendance.confirmed' => 'Attendance recorded',
            'attendance.late' => 'Late arrival',
            'attendance.missing_checkout' => 'Missing check-out',
            'session.opened' => 'Attendance session opened',
            'schedule.published' => 'Schedule published',
            'schedule.reminder' => 'Upcoming shift or lesson',
            'leave.submitted' => 'Leave submitted',
            'leave.decided' => 'Leave decided',
            'field.assigned' => 'Field assignment',
            'field.alert' => 'Field activity alert',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }
}
