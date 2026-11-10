<?php

namespace App\Workforce;

/**
 * Workforce notification events. The organisation chooses channels and
 * categories. A person may opt out of optional events only.
 * Push stays a plug. SMS uses the platform transmitter.
 */
final class WorkforceEvents
{
    /**
     * @return array<string, array{label: string, category: string, mandatory: bool, reminder: bool}>
     */
    public static function definitions(): array
    {
        return [
            'attendance.confirmed' => self::row('Attendance recorded', 'attendance', false, false),
            'attendance.late' => self::row('Late arrival', 'attendance', false, false),
            'attendance.rejected' => self::row('Attendance not accepted', 'attendance', true, false),
            'attendance.missing_checkout' => self::row('Missing check-out', 'attendance', true, false),
            'attendance.missed' => self::row('Missed attendance', 'attendance', false, true),
            'session.opened' => self::row('Attendance session opened', 'attendance', false, false),
            'schedule.published' => self::row('Schedule assigned', 'schedule', true, false),
            'schedule.changed' => self::row('Schedule changed or removed', 'schedule', true, false),
            'schedule.reminder' => self::row('Upcoming shift or lesson', 'schedule', false, true),
            'leave.submitted' => self::row('Leave submitted', 'leave', true, false),
            'leave.amended' => self::row('Leave amended', 'leave', true, false),
            'leave.decided' => self::row('Leave decided', 'leave', true, false),
            'leave.balance_warning' => self::row('Leave balance warning', 'leave', false, false),
            'leave.pending_reminder' => self::row('Leave waiting for a decision', 'leave', false, false),
            'leave.balance_expiring' => self::row('Leave balance expiring', 'leave', false, false),
            'field.assigned' => self::row('Field assignment', 'attendance', true, false),
            'field.alert' => self::row('Field activity alert', 'attendance', false, false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function catalog(): array
    {
        return array_map(fn (array $definition): string => $definition['label'], self::definitions());
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'leave' => 'Leave',
            'schedule' => 'Schedule',
            'attendance' => 'Attendance',
            'announcements' => 'Announcements',
        ];
    }

    /**
     * @return array{label: string, category: string, mandatory: bool, reminder: bool}|null
     */
    public static function definition(string $event): ?array
    {
        return self::definitions()[$event] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function eventsIn(string $category): array
    {
        return array_keys(array_filter(
            self::definitions(),
            fn (array $definition): bool => $definition['category'] === $category,
        ));
    }

    /**
     * @return array{label: string, category: string, mandatory: bool, reminder: bool}
     */
    private static function row(string $label, string $category, bool $mandatory, bool $reminder): array
    {
        return [
            'label' => $label,
            'category' => $category,
            'mandatory' => $mandatory,
            'reminder' => $reminder,
        ];
    }
}
