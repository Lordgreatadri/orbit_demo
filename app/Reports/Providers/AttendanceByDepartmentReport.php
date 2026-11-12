<?php

namespace App\Reports\Providers;

use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Workforce\WorkforceReport;
use Generator;

final class AttendanceByDepartmentReport extends AbstractReportProvider
{
    public function __construct(private WorkforceReport $reports) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'attendance-by-department',
            title: 'Attendance by department',
            description: 'Department-level attendance status totals for the selected period.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $scope = $filters->scope;
        $rows = $this->reports->departmentMix($scope->organization, $scope->from, $scope->to, $scope, null);
        $exportRows = array_map(fn (array $row): array => [
            'department' => $row['name'],
            'present' => $row['present'],
            'late' => $row['late'],
            'on_leave' => $row['on_leave'],
            'incomplete' => $row['incomplete'],
            'excused' => $row['excused'],
            'absent' => $row['absent'],
            'total' => $row['total'],
        ], $rows);

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Departments', number_format(count($rows)), icon: 'building'),
                new ReportKpi('Records', number_format(array_sum(array_column($rows, 'total'))), icon: 'chart'),
                new ReportKpi('Late records', number_format(array_sum(array_column($rows, 'late'))), icon: 'clock', tone: 'warning'),
            ],
            columns: $this->columns([
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'present', 'label' => 'Present'],
                ['key' => 'late', 'label' => 'Late'],
                ['key' => 'on_leave', 'label' => 'On leave'],
                ['key' => 'incomplete', 'label' => 'Incomplete'],
                ['key' => 'excused', 'label' => 'Excused'],
                ['key' => 'absent', 'label' => 'Absent'],
                ['key' => 'total', 'label' => 'Records'],
            ]),
            rows: $exportRows,
            emptyMessage: 'No department attendance totals for this period.',
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        yield from $this->yieldRows($this->build($filters)->rowArrays());
    }
}
