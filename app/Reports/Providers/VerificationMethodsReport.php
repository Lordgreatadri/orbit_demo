<?php

namespace App\Reports\Providers;

use App\Models\AttendanceEvent;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Support\CalendarDay;
use App\Workforce\WorkforceReport;
use Generator;

final class VerificationMethodsReport extends AbstractReportProvider
{
    public function __construct(private WorkforceReport $reports) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'verification-methods',
            title: 'Verification methods',
            description: 'Accepted check-in verification methods for the selected period.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location', 'person', 'method'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $scope = $filters->scope;
        $mix = $this->reports->methodMix($scope->organization, $scope->from, $scope->to, $scope);

        if ($filters->method !== null) {
            $mix = array_values(array_filter($mix, fn (array $row): bool => $row['key'] === $filters->method));
        }

        $rows = array_map(fn (array $row): array => [
            'method' => $row['label'],
            'accepted_check_ins' => $row['total'],
        ], $mix);

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Accepted check-ins', number_format(array_sum(array_column($rows, 'accepted_check_ins'))), icon: 'check-circle', tone: 'success'),
                new ReportKpi('Methods with activity', number_format(count(array_filter($rows, fn (array $row): bool => $row['accepted_check_ins'] > 0))), icon: 'chart'),
            ],
            columns: $this->columns([
                ['key' => 'method', 'label' => 'Method'],
                ['key' => 'accepted_check_ins', 'label' => 'Accepted check-ins'],
            ]),
            rows: $rows,
            emptyMessage: 'No accepted verification methods in this period.',
            meta: ['events' => $this->eventCount($filters)],
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        yield from $this->yieldRows($this->build($filters)->rowArrays());
    }

    private function eventCount(ReportFilters $filters): int
    {
        $scope = $filters->scope;

        return (int) AttendanceEvent::query()
            ->join('attendance_records', 'attendance_records.id', '=', 'attendance_events.attendance_record_id')
            ->where('attendance_events.organization_id', $scope->organization->getKey())
            ->where('attendance_events.type', 'check_in')
            ->where('attendance_events.outcome', 'accepted')
            ->where('attendance_records.work_date', '>=', $scope->from->toDateString())
            ->where('attendance_records.work_date', '<', CalendarDay::following($scope->to->toDateString()))
            ->when($scope->departmentIds !== null, fn ($query) => $query->whereIn(
                'attendance_records.department_id',
                $scope->departmentIds === [] ? [-1] : $scope->departmentIds,
            ))
            ->when($scope->staffMemberId !== null, fn ($query) => $query->where('attendance_records.staff_member_id', $scope->staffMemberId))
            ->when($scope->locationId !== null, fn ($query) => $query->where('attendance_records.location_id', $scope->locationId))
            ->when($filters->method !== null, fn ($query) => $query->where('attendance_events.method', $filters->method))
            ->count();
    }
}
