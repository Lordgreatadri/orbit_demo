<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Reports\Export\ExportManager;
use App\Reports\Providers\PlatformOrganisationStatisticsReport;
use App\Reports\ReportAudience;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, ReportRegistry $registry): View
    {
        abort_unless($request->user()?->can('platform.access'), 403);

        $providers = $registry->for($request->user(), ReportAudience::Platform);

        return view('platform.reports.index', [
            'definitions' => array_map(fn ($provider) => $provider->definition(), $providers),
        ]);
    }

    public function show(Request $request, string $report, ReportRegistry $registry): View
    {
        abort_unless($request->user()?->can('platform.access'), 403);

        $provider = $registry->get($report);
        abort_unless($provider->definition()->audience === ReportAudience::Platform, 404);
        abort_unless($provider->authorize($request->user()), 403);

        $filters = PlatformOrganisationStatisticsReport::platformFilters($request->user());
        $result = $provider->build($filters);

        return view('platform.reports.show', [
            'result' => $result,
            'query' => [],
        ]);
    }

    public function export(
        Request $request,
        string $report,
        ReportRegistry $registry,
        ExportManager $exports,
    ): StreamedResponse|Response|RedirectResponse {
        abort_unless($request->user()?->can('platform.access'), 403);

        $provider = $registry->get($report);
        abort_unless($provider->definition()->audience === ReportAudience::Platform, 404);
        abort_unless($provider->authorize($request->user()), 403);

        $format = ReportFormat::tryFrom((string) $request->query('format', 'csv')) ?? ReportFormat::Csv;
        $filters = PlatformOrganisationStatisticsReport::platformFilters($request->user());

        return $exports->download($provider, $filters, $format, $request->user());
    }
}
