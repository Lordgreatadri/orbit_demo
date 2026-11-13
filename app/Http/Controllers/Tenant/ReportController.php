<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRequest;
use App\Models\ReportExport;
use App\Reports\Export\ExportAuthorization;
use App\Reports\Export\ExportManager;
use App\Reports\ReportAudience;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportRegistry $registry, ExportAuthorization $authorization): View
    {
        abort_unless($request->user()?->can('analytics.view'), 403);

        $providers = $registry->for($request->user(), ReportAudience::Tenant);
        $grouped = [];

        foreach ($providers as $provider) {
            $definition = $provider->definition();
            $grouped[$definition->category][] = $definition;
        }

        $exports = ReportExport::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('user_id', $request->user()->getKey())
            ->where('status', ReportExport::STATUS_READY)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->limit(10)
            ->get()
            ->filter(fn (ReportExport $export): bool => $authorization->allows($request->user(), $export))
            ->values();

        return view('tenant.reports.index', [
            'grouped' => $grouped,
            'exports' => $exports,
        ]);
    }

    public function show(ReportRequest $request, string $report, ReportRegistry $registry): View
    {
        $provider = $registry->get($report);
        $filters = ReportFilters::fromInput($request->user(), $request->validated(), $provider->definition()->filters);
        $result = $provider->build($filters, (int) ($request->validated('page') ?? 1));

        return view('tenant.reports.show', [
            'result' => $result,
            'options' => $filters->options($provider->definition()->filters),
            'selected' => $filters->selected(),
            'query' => $filters->query(),
        ]);
    }

    public function export(
        ReportRequest $request,
        string $report,
        ReportRegistry $registry,
        ExportManager $exports,
    ): StreamedResponse|Response|RedirectResponse {
        $provider = $registry->get($report);
        $format = ReportFormat::tryFrom((string) $request->validated('format', 'csv')) ?? ReportFormat::Csv;
        $filters = ReportFilters::fromInput($request->user(), $request->validated(), $provider->definition()->filters);

        return $exports->download($provider, $filters, $format, $request->user());
    }

    public function download(Request $request, string $export, ExportAuthorization $authorization): StreamedResponse
    {
        abort_unless($request->user()?->can('analytics.view'), 403);

        $record = ReportExport::query()
            ->where('uuid', $export)
            ->where('organization_id', $request->user()->organization_id)
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();

        abort_unless($record->isReady() && ! $record->isExpired(), 404);
        abort_unless($record->ownsDiskPath($record->disk_path), 404);
        abort_unless($authorization->allows($request->user(), $record), 403);
        abort_unless(Storage::disk('local')->exists($record->disk_path), 404);

        $format = ReportFormat::from($record->format);

        return Storage::disk('local')->download(
            $record->disk_path,
            $record->report_key.'-export.'.$format->extension(),
            ['Content-Type' => $format->contentType()],
        );
    }
}
