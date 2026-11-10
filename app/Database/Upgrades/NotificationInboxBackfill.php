<?php

namespace App\Database\Upgrades;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills a missing inbox organisation from that notice's user, and a missing
 * event from that notice's own JSON payload. A stored value is not replaced.
 * A notice whose user is gone, or whose payload has no event, stays null.
 */
final class NotificationInboxBackfill
{
    public function run(): UpgradeReport
    {
        if (! Schema::hasColumn('notifications', 'organization_id') || ! Schema::hasColumn('notifications', 'event')) {
            return new UpgradeReport;
        }

        $report = new UpgradeReport(
            processed: (int) DB::table('notifications')->count(),
        );
        $updatedByOrganization = [];

        DB::table('notifications')
            ->where(function ($query): void {
                $query->whereNull('organization_id')->orWhereNull('event');
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($report, &$updatedByOrganization): void {
                $userIds = $rows
                    ->filter(fn ($row): bool => $row->organization_id === null && $row->notifiable_type === User::class)
                    ->pluck('notifiable_id')
                    ->unique()
                    ->all();
                $organizations = $userIds === []
                    ? collect()
                    : DB::table('users')->whereIn('id', $userIds)->pluck('organization_id', 'id');

                DB::transaction(function () use ($rows, $organizations, $report, &$updatedByOrganization): void {
                    foreach ($rows as $row) {
                        $changes = [];

                        if ($row->organization_id === null && $row->notifiable_type === User::class) {
                            $organizationId = $organizations->get($row->notifiable_id);

                            if ($organizationId !== null) {
                                $changes['organization_id'] = (int) $organizationId;
                            }
                        }

                        if ($row->event === null) {
                            $event = $this->eventFrom($row->data);

                            if ($event !== null) {
                                $changes['event'] = $event;
                            }
                        }

                        if ($changes === []) {
                            continue;
                        }

                        $update = DB::table('notifications')->where('id', $row->id);

                        if (isset($changes['organization_id'])) {
                            $update->whereNull('organization_id');
                        }

                        if (isset($changes['event'])) {
                            $update->whereNull('event');
                        }

                        if ($update->update($changes + ['updated_at' => now()]) !== 1) {
                            continue;
                        }

                        $report->updated++;
                        $organizationId = $changes['organization_id'] ?? $row->organization_id;

                        if ($organizationId === null) {
                            continue;
                        }

                        $organizationId = (int) $organizationId;
                        $updatedByOrganization[$organizationId] = ($updatedByOrganization[$organizationId] ?? 0) + 1;
                    }
                });
            });

        $report->skipped = max(0, $report->processed - $report->updated - $report->errors);
        UpgradeAudit::summarize('notifications.inbox_backfilled', $updatedByOrganization);

        return $report;
    }

    private function eventFrom(mixed $data): ?string
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data) || ! isset($data['event']) || ! is_scalar($data['event'])) {
            return null;
        }

        $event = trim((string) $data['event']);

        return $event === '' ? null : mb_substr($event, 0, 64);
    }
}
