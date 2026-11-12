<?php

namespace App\Reports\Providers;

use App\Analytics\OperationalDigest;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Workforce\WorkforceReport;
use Generator;

final class AttendanceSummaryReport extends AbstractReportProvider
{
    public function __construct(
        private WorkforceReport $reports,
        private OperationalDigest $digest,
    ) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'attendance-summary',
            title: 'Attendance summary',
            description: 'Operational attendance totals for the selected period, including today’s position and status mix.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location', 'person'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $scope = $filters->scope;
        $today = $this->digest->today($scope);
        $mix = $this->reports->statusMix($scope->organization, $scope->from, $scope->to, $scope);
        $rows = array_map(fn (array $row): array => [
            'status' => $row['label'],
            'records' => $row['total'],
        ], $mix);

        $total = array_sum(array_column($rows, 'records'));
        $findings = [];
        if ($today['late'] > 0) {
            $findings[] = number_format($today['late']).' late today.';
        }
        if ($today['incomplete'] > 0) {
            $findings[] = number_format($today['incomplete']).' incomplete today.';
        }
        if ($total === 0) {
            $findings[] = 'No attendance records in this range.';
        }

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Present today', number_format($today['present']), icon: 'check-circle', tone: 'success'),
                new ReportKpi('Late today', number_format($today['late']), icon: 'clock', tone: 'warning', emphasis: $today['late'] > 0 ? 'warning' : null),
                new ReportKpi('Absent today', number_format($today['absent']), hint: ($today['absent_basis'] ?? 'derived') === 'derived' ? 'Derived from active people without a covering record' : 'Stored absent status', icon: 'alert', tone: 'danger'),
                new ReportKpi('Records in range', number_format($total), icon: 'chart', tone: 'accent'),
            ],
            columns: $this->columns([
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'records', 'label' => 'Records'],
            ]),
            rows: $rows,
            emptyMessage: 'No attendance status totals for this period.',
            findings: $findings,
            meta: ['today' => $today, 'mix' => $mix],
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        yield from $this->yieldRows($this->build($filters)->rowArrays());
    }
}
