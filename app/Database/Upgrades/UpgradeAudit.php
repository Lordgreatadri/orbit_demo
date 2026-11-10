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
     */
    public static function summarize(string $event, array $updatedByOrganization): void
    {
        foreach ($updatedByOrganization as $organizationId => $updated) {
            if ($updated < 1) {
                continue;
            }

            Audit::log($event, null, ['updated' => $updated], null, (int) $organizationId);
        }
    }
}
