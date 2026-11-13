<?php

namespace App\Jobs;

use App\Jobs\Concerns\IsTenantAware;
use App\Models\ReportExport;
use App\Reports\Contracts\ReportProvider;
use App\Reports\Export\ExportManager;
use App\Reports\ReportAudience;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
        $export = ReportExport::claim($this->exportId, $this->organizationId);

        if ($export === null) {
            return;
        }

        $path = null;

        try {
            $user = $export->user()->first();

            if ($user === null) {
                throw new RuntimeException('Export requester is missing.');
            }

            if ((int) $user->organization_id !== (int) $export->organization_id) {
                throw new RuntimeException('Export requester is outside the export organisation.');
            }

            $provider = $registry->get($export->report_key);

            if ($provider->definition()->audience !== ReportAudience::Tenant) {
                throw new RuntimeException('Queued exports are limited to tenant reports.');
            }

            if (! $provider->authorize($user)) {
                throw new RuntimeException('Export requester is not authorised for this report.');
            }

            $format = ReportFormat::from($export->format);
            $filters = ReportFilters::fromInput(
                $user,
                $export->filters ?? [],
                $provider->definition()->filters,
            );

            if ((int) $filters->scope->organization->getKey() !== (int) $export->organization_id) {
                throw new RuntimeException('Rebuilt report scope left the export organisation.');
            }

            $directory = $export->artifactDirectory();
            $path = $directory.'/'.$export->uuid.'.'.$format->extension();

            if (! $export->ownsDiskPath($path)) {
                throw new RuntimeException('Export artifact path is not owned by the requester.');
            }

            Storage::disk('local')->makeDirectory($directory);
            $absolute = Storage::disk('local')->path($path);
            $this->writeExport($exports, $provider, $filters, $format, $absolute);

            $export->forceFill([
                'status' => ReportExport::STATUS_READY,
                'disk_path' => $path,
                'generation_scope' => $filters->scope->toGenerationScope(),
            ])->save();
        } catch (Throwable $exception) {
            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            $export->deleteArtifact();
            $export->forceFill([
                'status' => ReportExport::STATUS_FAILED,
                'failure_reason' => 'Export failed.',
                'disk_path' => null,
            ])->save();

            report($exception);

            throw $exception;
        }
    }

    protected function writeExport(
        ExportManager $exports,
        ReportProvider $provider,
        ReportFilters $filters,
        ReportFormat $format,
        string $absolute,
    ): void {
        $exports->writeToPath($provider, $filters, $format, $absolute);
    }
}
