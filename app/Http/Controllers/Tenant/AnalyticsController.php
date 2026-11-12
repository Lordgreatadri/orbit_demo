<?php

namespace App\Http\Controllers\Tenant;

use App\Analytics\AnalyticsScope;
use App\Analytics\ChartPalette;
use App\Analytics\OperationalDigest;
use App\Contracts\OperationalInsightProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\AnalyticsRangeRequest;
use App\Models\AttendanceRecord;
use App\Models\Location;
use App\Support\CalendarDay;
use App\Workforce\WorkforceReport;
use Illuminate\Contracts\View\View;
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

    public function export(AnalyticsRangeRequest $request): StreamedResponse
    {
        $user = $request->user();
        [$fromDate, $toDate] = $request->range();
        $scope = AnalyticsScope::forUser(
            $user,
            $fromDate->toDateString(),
            $toDate->toDateString(),
            $request->validated('department'),
            $request->validated('location'),
        );
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();
        $filename = 'attendance-'.$from.'-'.$to.'.csv';

        return response()->streamDownload(function () use ($from, $to, $scope): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Staff number', 'Name', 'Department', 'Location', 'Status', 'Check in', 'Check out', 'Late minutes']);

            $query = AttendanceRecord::query()
                ->with(['staffMember:id,staff_number,first_name,last_name', 'department:id,name', 'location:id,name'])
                ->where('organization_id', $scope->organization->getKey())
                ->where('work_date', '>=', $from)
                ->where('work_date', '<', CalendarDay::following($to))
                ->when($scope->departmentIds !== null, fn ($rows) => $rows->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds))
                ->when($scope->staffMemberId !== null, fn ($rows) => $rows->where('staff_member_id', $scope->staffMemberId))
                ->when($scope->locationId !== null, fn ($rows) => $rows->where('location_id', $scope->locationId))
                ->orderBy('work_date')
                ->orderBy('id');

            $query->chunk(200, function ($records) use ($handle): void {
                foreach ($records as $record) {
                    fputcsv($handle, array_map($this->spreadsheetCell(...), [
                        $record->work_date->toDateString(),
                        $record->staffMember?->staff_number,
                        $record->staffMember?->name,
                        $record->department?->name,
                        $record->location?->name,
                        $record->status->label(),
                        $record->check_in_at?->toIso8601String(),
                        $record->check_out_at?->toIso8601String(),
                        $record->late_minutes,
                    ]));
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  list<array{key: string, label: string, total: int}>  $rows
     * @return list<array{key: string, label: string, total: int, ink: string}>
     */
    private function paint(array $rows): array
    {
        return array_map(fn (array $row): array => $row + ['ink' => ChartPalette::color($row['key'])], $rows);
    }

    /**
     * Spreadsheet tools treat a cell starting with =, +, -, or @ as a formula.
     * A leading apostrophe keeps the value as text.
     */
    private function spreadsheetCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        $lead = ltrim($value, " \t\r\n");

        if ($lead !== '' && in_array($lead[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
