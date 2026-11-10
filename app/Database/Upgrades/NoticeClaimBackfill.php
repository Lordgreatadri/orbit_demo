<?php

namespace App\Database\Upgrades;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records a pre-status delivery claim as sent.
 * A queued, sent, or failed claim is not changed.
 * Each row keeps its own organisation.
 */
final class NoticeClaimBackfill
{
    public function run(): UpgradeReport
    {
        if (! Schema::hasTable('workforce_notice_deliveries') || ! Schema::hasColumn('workforce_notice_deliveries', 'status')) {
            return new UpgradeReport;
        }

        $report = new UpgradeReport(
            processed: (int) DB::table('workforce_notice_deliveries')->count(),
        );
        $updatedByOrganization = [];

        DB::table('workforce_notice_deliveries')
            ->whereNull('status')
            ->orderBy('id')
            ->select(['id', 'organization_id'])
            ->chunkById(200, function ($rows) use ($report, &$updatedByOrganization): void {
                DB::transaction(function () use ($rows, $report, &$updatedByOrganization): void {
                    $pending = DB::table('workforce_notice_deliveries')
                        ->whereIn('id', $rows->pluck('id')->all())
                        ->whereNull('status')
                        ->get(['id', 'organization_id']);

                    if ($pending->isEmpty()) {
                        return;
                    }

                    $affected = DB::table('workforce_notice_deliveries')
                        ->whereIn('id', $pending->pluck('id')->all())
                        ->whereNull('status')
                        ->update([
                            'status' => 'sent',
                            'updated_at' => now(),
                        ]);

                    $report->updated += $affected;

                    if ($affected !== $pending->count()) {
                        return;
                    }

                    foreach ($pending as $row) {
                        $organizationId = (int) $row->organization_id;
                        $updatedByOrganization[$organizationId] = ($updatedByOrganization[$organizationId] ?? 0) + 1;
                    }
                });
            });

        $report->skipped = max(0, $report->processed - $report->updated - $report->errors);
        UpgradeAudit::summarize('workforce.notice_claims_recorded', $updatedByOrganization);

        return $report;
    }
}
