<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AttendanceStatus;
use App\Enums\WorkType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\AnalyticsRangeRequest;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Workforce\WorkforceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(AnalyticsRangeRequest $request, WorkforceReport $reports): View
    {
        $organization = $request->user()->organization;
        [$from, $to] = $request->range();

        $byDepartment = AttendanceRecord::query()
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('department_id')
            ->select('department_id', 'status', DB::raw('count(*) as total'))
            ->groupBy('department_id', 'status')
            ->get();

        $byWorkType = AttendanceRecord::query()
            ->join('staff_members', 'staff_members.id', '=', 'attendance_records.staff_member_id')
            ->where('attendance_records.organization_id', $organization->getKey())
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->select('staff_members.work_type', DB::raw('count(*) as total'))
            ->groupBy('staff_members.work_type')
            ->pluck('total', 'work_type');

        $departments = Department::query()->pluck('name', 'id');
        $submittedFrom = $request->validated('from');
        $submittedTo = $request->validated('to');

        return view('tenant.analytics.index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rangeAdjusted' => ($submittedFrom && $submittedFrom !== $from->toDateString())
                || ($submittedTo && $submittedTo !== $to->toDateString()),
            'today' => $reports->day($organization),
            'trend' => $reports->trend($organization, $from, $to),
            'statuses' => $reports->statusMix($organization, $from, $to),
            'methods' => $reports->methodMix($organization, $from, $to),
            'departments' => $byDepartment
                ->groupBy('department_id')
                ->map(function ($rows, $departmentId) use ($departments) {
                    $byStatus = $rows->mapWithKeys(fn ($row) => [
                        $row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status => (int) $row->total,
                    ]);

                    return [
                        'name' => $departments[$departmentId] ?? 'Department',
                        'present' => (int) ($byStatus['present'] ?? 0),
                        'late' => (int) ($byStatus['late'] ?? 0),
                        'on_leave' => (int) ($byStatus['on_leave'] ?? 0),
                        'other' => (int) $byStatus->except(['present', 'late', 'on_leave'])->sum(),
                        'total' => (int) $rows->sum('total'),
                    ];
                })
                ->sortByDesc('total')
                ->take(8)
                ->values(),
            'workTypes' => collect(WorkType::cases())->map(fn (WorkType $type) => [
                'label' => $type->label(),
                'value' => (int) ($byWorkType[$type->value] ?? 0),
            ]),
            'leavePending' => LeaveRequest::query()->where('status', 'submitted')->count(),
        ]);
    }

    public function export(AnalyticsRangeRequest $request): StreamedResponse
    {
        [$fromDate, $toDate] = $request->range();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $filename = 'attendance-'.$from.'-'.$to.'.csv';

        return response()->streamDownload(function () use ($from, $to): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Staff number', 'Name', 'Department', 'Location', 'Status', 'Check in', 'Check out', 'Late minutes']);

            AttendanceRecord::query()
                ->with(['staffMember:id,staff_number,first_name,last_name', 'department:id,name', 'location:id,name'])
                ->whereBetween('work_date', [$from, $to])
                ->orderBy('work_date')
                ->orderBy('id')
                ->chunk(200, function ($records) use ($handle): void {
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
