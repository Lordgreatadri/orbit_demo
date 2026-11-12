<?php

namespace App\Reports\Providers;

use App\Models\AttendanceRecord;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Support\CalendarDay;
use Generator;
use Illuminate\Database\Eloquent\Builder;

final class AttendanceDetailReport extends AbstractReportProvider
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'attendance-detail',
            title: 'Attendance detail',
            description: 'Row-level attendance register for the selected period and scope.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location', 'person', 'status'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx],
            paginated: true,
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $timezone = $filters->scope->organization->timezone ?: 'UTC';
        $paginator = $this->baseQuery($filters)
            ->with(['staffMember:id,staff_number,first_name,last_name', 'department:id,name', 'location:id,name'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->paginate(50, ['*'], 'page', $page)
            ->withQueryString();

        $rows = collect($paginator->items())->map(fn (AttendanceRecord $record): array => $this->mapRecord($record, $timezone))->all();
        $paged = $paginator->setCollection(collect($rows));

        return new ReportResult(
            definition: $this->definition(),
            scope: $filters->scope,
            from: $filters->scope->from->toDateString(),
            to: $filters->scope->to->toDateString(),
            rangeAdjusted: $filters->rangeAdjusted,
            kpis: [
                new ReportKpi('Matching records', number_format($paginator->total()), icon: 'document-check'),
            ],
            columns: $this->columns([
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff_number', 'label' => 'Staff number'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'location', 'label' => 'Location'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'check_in', 'label' => 'Check in'],
                ['key' => 'check_out', 'label' => 'Check out'],
                ['key' => 'late_minutes', 'label' => 'Late minutes'],
            ]),
            rows: $paged,
            emptyMessage: 'No attendance records match these filters.',
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        $timezone = $filters->scope->organization->timezone ?: 'UTC';

        foreach ($this->baseQuery($filters)
            ->with(['staffMember:id,staff_number,first_name,last_name', 'department:id,name', 'location:id,name'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->cursor() as $record) {
            yield $this->mapRecord($record, $timezone);
        }
    }

    public function estimateRows(ReportFilters $filters): int
    {
        return (int) $this->baseQuery($filters)->count();
    }

    /**
     * @return Builder<AttendanceRecord>
     */
    private function baseQuery(ReportFilters $filters): Builder
    {
        $scope = $filters->scope;

        return AttendanceRecord::query()
            ->where('organization_id', $scope->organization->getKey())
            ->where('work_date', '>=', $scope->from->toDateString())
            ->where('work_date', '<', CalendarDay::following($scope->to->toDateString()))
            ->when($scope->departmentIds !== null, fn ($rows) => $rows->whereIn(
                'department_id',
                $scope->departmentIds === [] ? [-1] : $scope->departmentIds,
            ))
            ->when($scope->staffMemberId !== null, fn ($rows) => $rows->where('staff_member_id', $scope->staffMemberId))
            ->when($scope->locationId !== null, fn ($rows) => $rows->where('location_id', $scope->locationId))
            ->when($filters->status !== null, fn ($rows) => $rows->where('status', $filters->status));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecord(AttendanceRecord $record, string $timezone): array
    {
        return [
            'date' => $record->work_date?->toDateString(),
            'staff_number' => $record->staffMember?->staff_number,
            'name' => $record->staffMember?->name,
            'department' => $record->department?->name,
            'location' => $record->location?->name,
            'status' => $record->status?->label(),
            'check_in' => $record->check_in_at?->timezone($timezone)->format('Y-m-d H:i'),
            'check_out' => $record->check_out_at?->timezone($timezone)->format('Y-m-d H:i'),
            'late_minutes' => $record->late_minutes,
        ];
    }
}
