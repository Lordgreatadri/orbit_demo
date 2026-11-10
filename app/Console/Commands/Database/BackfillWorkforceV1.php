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

        $balanceChanges = LegacyWorkforceBackfill::leaveAccounts();
        LegacyWorkforceBackfill::attendanceLinks();

        $policiesAfter = $this->counts($this->policies());
        $linksAfter = $this->counts($this->unlinked());
        $adjustmentsAfter = $this->counts(LeaveAdjustment::withoutTenantScope()->getQuery());
        $updatedByOrganization = [];
        $detailsByOrganization = [];
        $processed = 0;
        $linked = 0;
        $policyTotal = 0;
        $linkTotal = 0;
        $adjustmentTotal = 0;
        $balanceTotal = 0;

        foreach (array_unique([
            ...array_keys($policiesBefore),
            ...array_keys($linksBefore),
            ...array_keys($adjustmentsBefore),
            ...array_keys($policiesAfter),
            ...array_keys($linksAfter),
            ...array_keys($adjustmentsAfter),
            ...array_keys($balanceChanges),
        ]) as $organizationId) {
            $organizationId = (int) $organizationId;
            $policies = ($policiesBefore[$organizationId] ?? 0) - ($policiesAfter[$organizationId] ?? 0);
            $links = ($linksBefore[$organizationId] ?? 0) - ($linksAfter[$organizationId] ?? 0);
            $adjustments = ($adjustmentsAfter[$organizationId] ?? 0) - ($adjustmentsBefore[$organizationId] ?? 0);
            $balances = $balanceChanges[$organizationId] ?? 0;
            $processed += ($policiesBefore[$organizationId] ?? 0) + ($linksBefore[$organizationId] ?? 0) + $balances;
            $linked += $policies + $links + $balances;
            $delta = $policies + $links + $adjustments + $balances;
            $policyTotal += $policies;
            $linkTotal += $links;
            $adjustmentTotal += $adjustments;
            $balanceTotal += $balances;

            if ($delta > 0) {
                $updatedByOrganization[$organizationId] = $delta;
                $detailsByOrganization[$organizationId] = [
                    'policy_links' => $policies,
                    'attendance_links' => $links,
                    'adjustments' => $adjustments,
                    'balances' => $balances,
                ];
            }
        }

        UpgradeAudit::summarize('workforce.legacy_backfilled', $updatedByOrganization, $detailsByOrganization);
        $this->line('Policy links: '.number_format($policyTotal));
        $this->line('Attendance links: '.number_format($linkTotal));
        $this->line('Adjustments: '.number_format($adjustmentTotal));
        $this->line('Balances: '.number_format($balanceTotal));

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
