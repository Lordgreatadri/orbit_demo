<?php

namespace App\Console\Commands\Database;

use App\Database\Upgrades\UpgradeAudit;
use App\Database\Upgrades\UpgradeReport;
use App\Models\AttendanceRecord;
use App\Models\LeaveAdjustment;
use App\Models\LeaveRequest;
use App\Workforce\LegacyWorkforceBackfill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class BackfillWorkforceV1 extends Command
{
    use ReportsDataUpgrade;

    protected $signature = 'orbit:data:backfill-workforce-v1';

    protected $description = 'Link legacy leave requests to a calendar-year policy and link unambiguous attendance rows';

    public function handle(): int
    {
        $policiesBefore = $this->counts($this->policies());
        $linksBefore = $this->counts($this->unlinked());
        $adjustmentsBefore = $this->counts(LeaveAdjustment::withoutTenantScope()->getQuery());

        LegacyWorkforceBackfill::leaveAccounts();
        LegacyWorkforceBackfill::attendanceLinks();

        $policiesAfter = $this->counts($this->policies());
        $linksAfter = $this->counts($this->unlinked());
        $adjustmentsAfter = $this->counts(LeaveAdjustment::withoutTenantScope()->getQuery());
        $updatedByOrganization = [];
        $processed = 0;
        $linked = 0;

        foreach (array_unique([
            ...array_keys($policiesBefore),
            ...array_keys($linksBefore),
            ...array_keys($adjustmentsBefore),
            ...array_keys($policiesAfter),
            ...array_keys($linksAfter),
            ...array_keys($adjustmentsAfter),
        ]) as $organizationId) {
            $organizationId = (int) $organizationId;
            $policies = ($policiesBefore[$organizationId] ?? 0) - ($policiesAfter[$organizationId] ?? 0);
            $links = ($linksBefore[$organizationId] ?? 0) - ($linksAfter[$organizationId] ?? 0);
            $adjustments = ($adjustmentsAfter[$organizationId] ?? 0) - ($adjustmentsBefore[$organizationId] ?? 0);
            $processed += ($policiesBefore[$organizationId] ?? 0) + ($linksBefore[$organizationId] ?? 0);
            $linked += $policies + $links;
            $delta = $policies + $links + $adjustments;

            if ($delta > 0) {
                $updatedByOrganization[$organizationId] = $delta;
            }
        }

        UpgradeAudit::summarize('workforce.legacy_backfilled', $updatedByOrganization);

        return $this->finish(new UpgradeReport(
            processed: $processed,
            updated: array_sum($updatedByOrganization),
            skipped: max(0, $processed - $linked),
        ));
    }

    /**
     * @return array<int, int>
     */
    private function counts(Builder|\Illuminate\Database\Query\Builder $query): array
    {
        return $query->clone()
            ->selectRaw('organization_id, count(*) as aggregate')
            ->groupBy('organization_id')
            ->pluck('aggregate', 'organization_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function policies(): Builder
    {
        return LeaveRequest::withoutTenantScope()->whereNull('leave_policy_id');
    }

    private function unlinked(): Builder
    {
        return AttendanceRecord::withoutTenantScope()
            ->whereNull('shift_assignment_id')
            ->whereNull('attendance_session_id')
            ->whereNotNull('check_in_at');
    }
}
