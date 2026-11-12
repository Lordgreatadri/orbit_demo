<?php

namespace App\Jobs;

use App\Jobs\Concerns\IsTenantAware;
use App\Models\ReportExport;
use App\Reports\Export\ExportManager;
use App\Reports\Providers\PlatformOrganisationStatisticsReport;
use App\Reports\ReportAudience;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use IsTenantAware;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $exportId,
        int $organizationId,
    ) {
        $this->organizationId = $organizationId;
    }

    public function handle(ReportRegistry $registry, ExportManager $exports): void
    {
        $export = ReportExport::query()->find($this->exportId);

        if ($export === null || $export->status === 'ready') {
            return;
        }

        $export->update(['status' => 'processing', 'failure_reason' => null]);

        try {
            $provider = $registry->get($export->report_key);
            $format = ReportFormat::from($export->format);
            $user = $export->user;

            abort_unless($provider->authorize($user), 403);

            $filters = $provider->definition()->audience === ReportAudience::Platform
                ? PlatformOrganisationStatisticsReport::platformFilters($user)
                : ReportFilters::fromInput($user, $export->filters ?? [], $provider->definition()->filters);

            $directory = 'report-exports/'.$export->organization_id.'/'.$export->user_id;
            $filename = $export->uuid.'.'.$format->extension();
            $path = $directory.'/'.$filename;

            Storage::disk('local')->makeDirectory($directory);
            $absolute = Storage::disk('local')->path($path);
            $exports->writeToPath($provider, $filters, $format, $absolute);

            $export->update([
                'status' => 'ready',
                'disk_path' => $path,
            ]);
        } catch (Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'failure_reason' => 'Export failed.',
            ]);

            report($exception);
        }
    }
}
