<?php

namespace App\Database\Upgrades;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies an already-read inbox row into notification_read_audits once.
 * The recipient must still belong to that notice's organisation.
 */
final class NotificationReadBackfill
{
    public function run(): UpgradeReport
    {
        if (! Schema::hasTable('notification_read_audits') || ! Schema::hasColumn('notifications', 'organization_id')) {
            return new UpgradeReport;
        }

        $report = new UpgradeReport(
            processed: (int) DB::table('notifications')->whereNotNull('read_at')->count(),
        );
        $updatedByOrganization = [];

        DB::table('notifications')
            ->whereNotNull('read_at')
            ->whereNotNull('organization_id')
            ->where('notifiable_type', User::class)
            ->whereExists(function ($query): void {
                $query->select(DB::raw('1'))
                    ->from('users')
                    ->whereColumn('users.id', 'notifications.notifiable_id')
                    ->whereColumn('users.organization_id', 'notifications.organization_id');
            })
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw('1'))
                    ->from('notification_read_audits')
                    ->whereColumn('notification_read_audits.notification_id', 'notifications.id')
                    ->whereColumn('notification_read_audits.user_id', 'notifications.notifiable_id')
                    ->whereColumn('notification_read_audits.organization_id', 'notifications.organization_id');
            })
            ->orderBy('id')
            ->select(['id', 'organization_id', 'notifiable_id', 'read_at'])
            ->chunkById(200, function ($rows) use ($report, &$updatedByOrganization): void {
                $now = now();
                $insert = [];
                $chunkCounts = [];

                foreach ($rows as $row) {
                    $insert[] = [
                        'organization_id' => $row->organization_id,
                        'notification_id' => $row->id,
                        'user_id' => $row->notifiable_id,
                        'read_at' => $row->read_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $organizationId = (int) $row->organization_id;
                    $chunkCounts[$organizationId] = ($chunkCounts[$organizationId] ?? 0) + 1;
                }

                $before = (int) DB::table('notification_read_audits')->count();
                DB::table('notification_read_audits')->insertOrIgnore($insert);
                $inserted = (int) DB::table('notification_read_audits')->count() - $before;
                $report->updated += $inserted;

                if ($inserted !== count($insert)) {
                    return;
                }

                foreach ($chunkCounts as $organizationId => $count) {
                    $updatedByOrganization[$organizationId] = ($updatedByOrganization[$organizationId] ?? 0) + $count;
                }
            });

        $report->skipped = max(0, $report->processed - $report->updated - $report->errors);
        UpgradeAudit::summarize('notifications.reads_backfilled', $updatedByOrganization);

        return $report;
    }
}
