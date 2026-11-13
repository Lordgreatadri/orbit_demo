<?php

use App\Integrations\Sms\SmsProviderRegistry;
use App\Models\ReportExport;
use App\Workforce\AttendanceEngine;
use App\Workforce\WorkforceNotifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attendance:close-open', function () {
    $closed = app(AttendanceEngine::class)->closeOpenRecords();
    $this->info('Marked '.$closed.' open attendance records as incomplete.');
})->purpose('Mark previous-day check-ins without a check-out as incomplete');

Schedule::command('attendance:close-open')->dailyAt('00:30');

Artisan::command('workforce:remind', function () {
    $notifier = app(WorkforceNotifier::class);
    $shifts = $notifier->remindDueShifts();
    $missed = $notifier->remindMissedAttendance();
    $pending = $notifier->remindPendingLeave();
    $expiring = $notifier->remindExpiringBalances();
    $this->info('Queued '.$shifts.' shift reminders, '.$missed.' missed-attendance notices, '.$pending.' pending-leave reminders, and '.$expiring.' balance reminders.');
})->purpose('Queue shift, missed attendance, pending leave, and expiring balance reminders');

Artisan::command('workforce:field-status', function () {
    $flagged = app(WorkforceNotifier::class)->flagDelayedField();
    $this->info('Marked '.$flagged.' field activities as delayed.');
})->purpose('Mark quiet field activities as delayed and notify the assignee');

Schedule::command('workforce:remind')->dailyAt('17:00');
Schedule::command('workforce:field-status')->hourly();

Artisan::command('sms:sync-providers', function () {
    $count = app(SmsProviderRegistry::class)->ensureRows();
    $this->info('SMS providers synced ('.$count.' registered). Existing settings were left in place.');
})->purpose('Create configuration rows for registered SMS adapters');

Artisan::command('reports:purge-exports', function () {
    $expired = ReportExport::query()
        ->where(function ($query): void {
            $query->where('expires_at', '<', now())
                ->orWhere(function ($stale): void {
                    $stale->whereIn('status', ['failed', 'processing'])
                        ->where('updated_at', '<', now()->subDay());
                });
        })
        ->orderBy('id')
        ->limit(200)
        ->get();

    $purged = 0;
    foreach ($expired as $export) {
        $export->deleteArtifact();
        $export->delete();
        $purged++;
    }

    $kept = array_flip(ReportExport::query()->whereNotNull('disk_path')->pluck('disk_path')->all());
    $orphans = 0;
    if (Storage::disk('local')->directoryExists('report-exports')) {
        foreach (Storage::disk('local')->allFiles('report-exports') as $file) {
            if (! isset($kept[$file])) {
                Storage::disk('local')->delete($file);
                $orphans++;
            }
        }
    }

    $this->info('Purged '.$purged.' expired or stale report exports and '.$orphans.' orphaned files.');
})->purpose('Delete expired report export artifacts and rows');

Schedule::command('reports:purge-exports')->hourly();
