<?php

namespace App\Reports\Providers;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Support\CalendarDay;
use Generator;

final class IncompleteAttendanceReport extends AbstractReportProvider
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'incomplete-attendance',
            title: 'Incomplete and open attendance',
            description: 'Attendance records marked incomplete or still missing a check-out.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location', 'person'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx],
            paginated: true,
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $timezone = $filters->scope->organization->timezone ?: 'UTC';
        $rows = [];

        foreach ($this->query($filters)->cursor() as $record) {
            $rows[] = $this->map($record, $timezone);
        }

        $open = count(array_filter($rows, fn (array $row): bool => $row['check_out'] === null || $row['check_out'] === ''));

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Open or incomplete', number_format(count($rows)), icon: 'alert', tone: 'warning'),
                new ReportKpi('Missing check-out', number_format($open), icon: 'clock'),
            ],
            columns: $this->columns([
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff_number', 'label' => 'Staff number'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'check_in', 'label' => 'Check in'],
                ['key' => 'check_out', 'label' => 'Check out'],
            ]),
            rows: $rows,
            emptyMessage: 'No incomplete or open attendance records in this period.',
            page: $page,
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        $timezone = $filters->scope->organization->timezone ?: 'UTC';

        foreach ($this->query($filters)->cursor() as $record) {
            yield $this->map($record, $timezone);
        }
    }

    public function estimateRows(ReportFilters $filters): int
    {
        return (int) $this->query($filters)->count();
    }

    private function query(ReportFilters $filters)
    {
        $scope = $filters->scope;

        return AttendanceRecord::query()
            ->with(['staffMember:id,staff_number,first_name,last_name', 'department:id,name'])
            ->where('organization_id', $scope->organization->getKey())
            ->where('work_date', '>=', $scope->from->toDateString())
            ->where('work_date', '<', CalendarDay::following($scope->to->toDateString()))
            ->where(function ($query): void {
                $query->where('status', AttendanceStatus::Incomplete->value)
                    ->orWhere(function ($open): void {
                        $open->whereNotNull('check_in_at')->whereNull('check_out_at');
                    });
            })
            ->when($scope->departmentIds !== null, fn ($rows) => $rows->whereIn(
                'department_id',
                $scope->departmentIds === [] ? [-1] : $scope->departmentIds,
            ))
            ->when($scope->staffMemberId !== null, fn ($rows) => $rows->where('staff_member_id', $scope->staffMemberId))
            ->when($scope->locationId !== null, fn ($rows) => $rows->where('location_id', $scope->locationId))
            ->orderBy('work_date')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function map(AttendanceRecord $record, string $timezone): array
    {
        return [
            'date' => $record->work_date?->toDateString(),
            'staff_number' => $record->staffMember?->staff_number,
            'name' => $record->staffMember?->name,
            'department' => $record->department?->name,
            'status' => $record->status?->label(),
            'check_in' => $record->check_in_at?->timezone($timezone)->format('Y-m-d H:i'),
            'check_out' => $record->check_out_at?->timezone($timezone)->format('Y-m-d H:i'),
        ];
    }
}
