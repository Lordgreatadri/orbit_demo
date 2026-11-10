<?php

namespace App\Database\Upgrades;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills day_count and covered_dates only where both are still unset.
 * A request that already stores a count is not recalculated.
 */
final class LeaveRequestCoverageBackfill
{
    public function run(): UpgradeReport
    {
        $report = new UpgradeReport(
            processed: (int) DB::table('leave_requests')->count(),
        );
        $updatedByOrganization = [];

        DB::table('leave_requests')
            ->where('day_count', 0)
            ->whereNull('covered_dates')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($report, &$updatedByOrganization): void {
                DB::transaction(function () use ($rows, $report, &$updatedByOrganization): void {
                    foreach ($rows as $row) {
                        $dates = $this->dates((string) $row->starts_on, (string) $row->ends_on);

                        if ($dates === []) {
                            continue;
                        }

                        $updated = DB::table('leave_requests')
                            ->where('id', $row->id)
                            ->where('day_count', 0)
                            ->whereNull('covered_dates')
                            ->update([
                                'day_count' => count($dates),
                                'covered_dates' => json_encode($dates),
                            ]);

                        if ($updated !== 1) {
                            continue;
                        }

                        $report->updated++;
                        $organizationId = (int) $row->organization_id;
                        $updatedByOrganization[$organizationId] = ($updatedByOrganization[$organizationId] ?? 0) + 1;
                    }
                });
            });

        $report->skipped = max(0, $report->processed - $report->updated - $report->errors);
        UpgradeAudit::summarize('leave.coverage_backfilled', $updatedByOrganization);

        return $report;
    }

    /**
     * @return list<string>
     */
    private function dates(string $startsOn, string $endsOn): array
    {
        $start = Carbon::parse(substr($startsOn, 0, 10));
        $end = Carbon::parse(substr($endsOn, 0, 10));

        if ($end->lt($start)) {
            return [];
        }

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end) && count($dates) < 366) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
