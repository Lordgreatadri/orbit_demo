<?php

namespace App\Database\Upgrades;

use App\Support\Audit;

/**
 * One activity row per organisation when a data upgrade changes business rows.
 */
final class UpgradeAudit
{
    /**
     * @param  array<int, int>  $updatedByOrganization
     * @param  array<int, array<string, int>>  $detailsByOrganization
     */
    public static function summarize(string $event, array $updatedByOrganization, array $detailsByOrganization = []): void
    {
        foreach ($updatedByOrganization as $organizationId => $updated) {
            if ($updated < 1) {
                continue;
            }

            $properties = ['updated' => $updated];

            foreach ($detailsByOrganization[$organizationId] ?? [] as $key => $value) {
                if ($value > 0) {
                    $properties[$key] = $value;
                }
            }

            Audit::log($event, null, $properties, null, (int) $organizationId);
        }
    }
}
