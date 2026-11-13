<?php

namespace App\Reports\Export;

use App\Analytics\AnalyticsScope;
use App\Models\ReportExport;
use App\Models\User;
use App\Reports\ReportRegistry;
use InvalidArgumentException;

/**
 * Download-time check. Ownership of the file is not enough: the requester's
 * current permission and AnalyticsScope must still cover the scope that
 * produced the artifact.
 */
final class ExportAuthorization
{
    public function __construct(private ReportRegistry $registry) {}

    public function allows(User $user, ReportExport $export): bool
    {
        if ((int) $user->getKey() !== (int) $export->user_id) {
            return false;
        }

        if ((int) $user->organization_id !== (int) $export->organization_id) {
            return false;
        }

        try {
            $provider = $this->registry->get($export->report_key);
        } catch (InvalidArgumentException) {
            return false;
        }

        if (! $provider->authorize($user)) {
            return false;
        }

        return AnalyticsScope::forUser($user)->coversGeneration($export->generation_scope);
    }
}
