<?php

namespace App\Http\Controllers\Tenant;

use App\Analytics\AnalyticsScope;
use App\Analytics\ChartPalette;
use App\Analytics\OperationalDigest;
use App\Contracts\OperationalInsightProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\AnalyticsRangeRequest;
use App\Models\Location;
use App\Reports\Export\ExportManager;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use App\Workforce\WorkforceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(
        AnalyticsRangeRequest $request,
        WorkforceReport $reports,
        OperationalDigest $digest,
        OperationalInsightProvider $insights,
    ): View {
        $user = $request->user();
        [$from, $to] = $request->range();
        $scope = AnalyticsScope::forUser(
            $user,
            $from->toDateString(),
            $to->toDateString(),
            $request->validated('department'),
            $request->validated('location'),
        );
        $organization = $scope->organization;
        $submittedFrom = $request->validated('from');
        $submittedTo = $request->validated('to');

        $statuses = $this->paint($reports->statusMix($organization, $from, $to, $scope));
        $methods = $this->paint($reports->methodMix($organization, $from, $to, $scope));
        $today = $digest->today($scope);
        $leave = $digest->leave($scope);

        return view('tenant.analytics.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rangeAdjusted' => ($submittedFrom && $submittedFrom !== $from->toDateString())
                || ($submittedTo && $submittedTo !== $to->toDateString()),
            'scopeLabel' => $scope->label(),
            'departments' => $scope->selectableDepartments(),
            'locations' => Location::query()->where('organization_id', $organization->getKey())->orderBy('name')->get(['uuid', 'name']),
            'selectedDepartment' => $request->validated('department'),
            'selectedLocation' => $request->validated('location'),
            'today' => $today,
            'trend' => $reports->trend($organization, $from, $to, $scope),
            'statuses' => $statuses,
            'methods' => $methods,
            'departmentRows' => $reports->departmentMix($organization, $from, $to, $scope),
            'workTypes' => collect($reports->workTypeMix($organization, $from, $to, $scope)),
            'leavePending' => $leave['pending'],
            'leaveDecisions' => $leave['decisions'],
            'leaveSummary' => $leave,
            'adherence' => $reports->scheduleAdherence($organization, $from, $to, $scope),
            'arrangements' => $reports->arrangementMix($organization, $from, $to, $scope),
            'schedule' => $digest->schedule($scope),
            'notifications' => $digest->notifications($scope),
            'insights' => $insights->forScope($scope, $today),
        ]);
    }

    public function export(
        AnalyticsRangeRequest $request,
        ReportRegistry $registry,
        ExportManager $exports,
    ): StreamedResponse|Response|RedirectResponse {
        $provider = $registry->get('attendance-detail');
        $filters = ReportFilters::fromInput($request->user(), [
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
            'department' => $request->validated('department'),
            'location' => $request->validated('location'),
        ], $provider->definition()->filters);

        return $exports->download($provider, $filters, ReportFormat::Csv, $request->user());
    }

    /**
     * @param  list<array{key: string, label: string, total: int}>  $rows
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    private function paint(array $rows): array
    {
        return array_map(fn (array $row): array => $row + ['ink' => ChartPalette::color($row['key'])], $rows);
    }
}
