<?php

namespace App\Reports\Export;

use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use App\Models\User;
use App\Reports\Contracts\ReportProvider;
use App\Reports\ReportAudience;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportManager
{
    public const QUEUE_THRESHOLD = 5000;

    public function __construct(
        private CsvExporter $csv,
        private XlsxExporter $xlsx,
        private PdfExporter $pdf,
    ) {}

    public function download(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportFormat $format,
        User $user,
    ): StreamedResponse|Response|RedirectResponse {
        abort_unless($provider->definition()->supports($format), 404);

        abort_unless($provider->authorize($user), 403);

        // Estimate is always computed server-side from the authorised filter bag.
        // Clients cannot submit a row count to force the sync or queued path.
        $estimate = $provider->estimateRows($filters);

        if ($format !== ReportFormat::Csv && $estimate > self::QUEUE_THRESHOLD) {
            abort_unless($provider->definition()->audience === ReportAudience::Tenant, 404);
            abort_unless($filters->scope->organization->getKey() !== null, 404);
            abort_unless((int) $user->organization_id === (int) $filters->scope->organization->getKey(), 403);

            $export = ReportExport::query()->create([
                'organization_id' => $filters->scope->organization->getKey(),
                'user_id' => $user->getKey(),
                'report_key' => $provider->definition()->key,
                'format' => $format->value,
                'filters' => $filters->query(),
                'generation_scope' => $filters->scope->toGenerationScope(),
                'status' => ReportExport::STATUS_QUEUED,
                'row_estimate' => $estimate,
                'expires_at' => now()->addDay(),
            ]);

            GenerateReportExport::dispatch($export->getKey(), (int) $filters->scope->organization->getKey());

            return redirect()
                ->route(
                    'org.reports.show',
                    ['report' => $provider->definition()->key] + $filters->query(),
                )
                ->with('status', 'Large export queued. Download it from the report exports list when ready.');
        }

        $result = $provider->build($filters);
        $filename = $provider->definition()->key.'-'.$result->from.'-'.$result->to.'.'.$format->extension();

        return match ($format) {
            ReportFormat::Csv => $this->csv->stream($provider, $filters, $result, $filename),
            ReportFormat::Xlsx => $this->xlsx->stream($provider, $filters, $result, $filename),
            ReportFormat::Pdf => $this->pdf->download($provider, $filters, $result, $filename),
        };
    }

    public function writeToPath(
        ReportProvider $provider,
        ReportFilters $filters,
        ReportFormat $format,
        string $path,
    ): void {
        $result = $provider->build($filters);

        match ($format) {
            ReportFormat::Csv => $this->csv->write($provider, $filters, $result, $path),
            ReportFormat::Xlsx => $this->xlsx->write($provider, $filters, $result, $path),
            ReportFormat::Pdf => $this->pdf->write($provider, $filters, $result, $path),
        };
    }
}
