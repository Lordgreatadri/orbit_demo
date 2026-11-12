<?php

namespace App\Analytics;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Platform figures that do not open a tenant workspace and do not describe
 * a tenant's attendance, leave, or schedules.
 */
final class PlatformPulse
{
    /**
     * Organisations created in each of the last eight weeks, oldest first.
     * The bucket is computed in PHP so SQLite and PostgreSQL share one query.
     *
     * @return list<array{label: string, total: int, key: string, ink: string}>
     */
    public function registrations(): array
    {
        $start = now()->startOfWeek()->subWeeks(7);
        $counts = [];

        foreach (Organization::query()->where('created_at', '>=', $start)->pluck('created_at') as $created) {
            $week = Carbon::parse($created)->startOfWeek()->toDateString();
            $counts[$week] = ($counts[$week] ?? 0) + 1;
        }

        $rows = [];
        $cursor = $start->copy();
        while ($cursor->lte(now()) && count($rows) < 8) {
            $key = $cursor->toDateString();
            $rows[] = [
                'key' => 'registrations',
                'label' => $cursor->format('j M'),
                'total' => (int) ($counts[$key] ?? 0),
                'ink' => ChartPalette::color('present'),
            ];
            $cursor->addWeek();
        }

        return $rows;
    }

    /**
     * @param  Collection<string, int|string>  $byStatus
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    public function statusMix($byStatus): array
    {
        return array_map(function (OrganizationStatus $status) use ($byStatus): array {
            return [
                'key' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($byStatus[$status->value] ?? 0),
                'ink' => match ($status) {
                    OrganizationStatus::Active => ChartPalette::color('present'),
                    OrganizationStatus::PendingVerification => ChartPalette::color('late'),
                    OrganizationStatus::Suspended => ChartPalette::color('absent'),
                    OrganizationStatus::Closed => ChartPalette::color('manual'),
                },
            ];
        }, OrganizationStatus::cases());
    }
}
