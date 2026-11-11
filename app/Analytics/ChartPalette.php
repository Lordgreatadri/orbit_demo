<?php

namespace App\Analytics;

/**
 * One colour meaning for attendance, verification, and chart series.
 * Colour is never the only carrier: every chart also prints the label.
 */
final class ChartPalette
{
    public static function color(string $key): string
    {
        return match ($key) {
            'present', 'qr', 'approved', 'sent', 'office' => 'var(--primary)',
            'late', 'incomplete', 'pending_verification', 'otp', 'submitted', 'queued' => 'var(--warning)',
            'absent', 'rejected', 'failed' => 'var(--danger)',
            'on_leave', 'ble', 'flexible' => 'var(--accent)',
            'excused', 'gps', 'delivered' => 'var(--success)',
            'wifi', 'remote', 'read' => 'var(--info)',
            'manual', 'field' => 'var(--fg-muted)',
            'device', 'draft' => 'var(--brand-deep)',
            default => 'var(--fg-subtle)',
        };
    }
}
