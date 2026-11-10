<?php

namespace App\Database\Upgrades;

use Illuminate\Support\Facades\DB;

/**
 * Fills leave-balance periods that are still empty or still on the column
 * default. A period an administrator has already set is left alone.
 * Each row uses its own year. Another organisation's calendar is never read.
 */
final class LeavePeriodBackfill
{
    public function run(): UpgradeReport
    {
        $report = new UpgradeReport(
            processed: (int) DB::table('leave_balances')->count(),
        );
        $updatedByOrganization = [];

        DB::table('leave_balances')
            ->where(function ($query): void {
                $query->whereNull('period_start')
                    ->orWhere(function ($query): void {
                        $query->where('period_start', 'like', '2000-01-01%')
                            ->where(function ($query): void {
                                $query->whereNull('period_end')
                                    ->orWhere('period_end', 'like', '2000-12-31%');
                            });
                    });
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($report, &$updatedByOrganization): void {
                $staffIds = $rows->pluck('staff_member_id')->unique()->all();
                $taken = [];

                DB::table('leave_balances')
                    ->whereIn('staff_member_id', $staffIds)
                    ->get(['id', 'staff_member_id', 'leave_type_id', 'period_start'])
                    ->each(function ($row) use (&$taken): void {
                        $day = substr((string) $row->period_start, 0, 10);
                        $taken[$row->staff_member_id.'|'.$row->leave_type_id.'|'.$day] = (int) $row->id;
                    });

                DB::transaction(function () use ($rows, $report, &$taken, &$updatedByOrganization): void {
                    foreach ($rows as $row) {
                        $year = (int) $row->year;

                        if ($year < 1) {
                            continue;
                        }

                        $start = sprintf('%04d-01-01', $year);
                        $key = $row->staff_member_id.'|'.$row->leave_type_id.'|'.$start;
                        $owner = $taken[$key] ?? null;

                        if ($owner !== null && $owner !== (int) $row->id) {
                            continue;
                        }

                        $updated = DB::table('leave_balances')->where('id', $row->id)->update([
                            'period_start' => $start,
                            'period_end' => sprintf('%04d-12-31', $year),
                        ]);

                        if ($updated !== 1) {
                            continue;
                        }

                        $report->updated++;
                        $taken[$key] = (int) $row->id;
                        $organizationId = (int) $row->organization_id;
                        $updatedByOrganization[$organizationId] = ($updatedByOrganization[$organizationId] ?? 0) + 1;
                    }
                });
            });

        $report->skipped = max(0, $report->processed - $report->updated - $report->errors);
        UpgradeAudit::summarize('leave.periods_backfilled', $updatedByOrganization);

        return $report;
    }
}
