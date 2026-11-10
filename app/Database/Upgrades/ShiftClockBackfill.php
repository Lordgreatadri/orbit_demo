<?php

namespace App\Database\Upgrades;

use Illuminate\Support\Facades\DB;

/**
 * Copies a shift's clock onto assignments that do not have one yet.
 * The shift must belong to the same organisation as the assignment.
 */
final class ShiftClockBackfill
{
    public function run(): UpgradeReport
    {
        $report = new UpgradeReport(
            processed: (int) DB::table('shift_assignments')->count(),
        );
        $updatedByOrganization = [];

        DB::table('shift_assignments')
            ->whereNull('starts_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($report, &$updatedByOrganization): void {
                $shifts = DB::table('shifts')
                    ->whereIn('id', $rows->pluck('shift_id')->unique()->all())
                    ->get()
                    ->keyBy('id');

                DB::transaction(function () use ($rows, $shifts, $report, &$updatedByOrganization): void {
                    foreach ($rows as $row) {
                        $shift = $shifts->get($row->shift_id);

                        if ($shift === null || (int) $shift->organization_id !== (int) $row->organization_id) {
                            continue;
                        }

                        $updated = DB::table('shift_assignments')
                            ->where('id', $row->id)
                            ->whereNull('starts_at')
                            ->update([
                                'starts_at' => $shift->starts_at,
                                'ends_at' => $shift->ends_at,
                                'crosses_midnight' => $shift->crosses_midnight,
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
        UpgradeAudit::summarize('schedule.clocks_backfilled', $updatedByOrganization);

        return $report;
    }
}
