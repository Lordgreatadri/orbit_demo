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

final class AttendanceByPersonReport extends AbstractReportProvider
{
    public function __construct(private WorkforceReport $reports) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'attendance-by-person',
            title: 'Attendance by person',
            description: 'Per-person attendance totals for the selected period.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location', 'person', 'status'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
            paginated: true,
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $scope = $filters->scope;
        $rows = $this->reports->byPerson($scope->organization, $scope->from, $scope->to, $scope);

        if ($filters->status !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => (int) ($row[$filters->status] ?? 0) > 0,
            ));
        }

        $people = count($rows);
        $latePeople = count(array_filter($rows, fn (array $row): bool => $row['late'] > 0));

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('People', number_format($people), icon: 'users'),
                new ReportKpi('With late arrivals', number_format($latePeople), icon: 'clock', tone: 'warning'),
                new ReportKpi('Total records', number_format(array_sum(array_column($rows, 'total'))), icon: 'chart'),
            ],
            columns: $this->columns([
                ['key' => 'staff_number', 'label' => 'Staff number'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'present', 'label' => 'Present'],
                ['key' => 'late', 'label' => 'Late'],
                ['key' => 'on_leave', 'label' => 'On leave'],
                ['key' => 'incomplete', 'label' => 'Incomplete'],
                ['key' => 'excused', 'label' => 'Excused'],
                ['key' => 'absent', 'label' => 'Absent'],
                ['key' => 'late_minutes', 'label' => 'Late minutes'],
                ['key' => 'total', 'label' => 'Records'],
            ]),
            rows: $rows,
            emptyMessage: 'No people with attendance records in this period.',
            page: $page,
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        $rows = $this->reports->byPerson(
            $filters->scope->organization,
            $filters->scope->from,
            $filters->scope->to,
            $filters->scope,
        );

        if ($filters->status !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => (int) ($row[$filters->status] ?? 0) > 0,
            ));
        }

        yield from $this->yieldRows($rows);
    }

    public function estimateRows(ReportFilters $filters): int
    {
        return count(iterator_to_array($this->exportRows($filters), false));
    }
}
