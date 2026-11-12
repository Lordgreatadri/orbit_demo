<?php

namespace App\Reports\Providers;

use App\Analytics\OperationalDigest;
use App\Enums\WorkArrangementKind;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Workforce\WorkforceReport;
use Generator;

final class ScheduleCoverageReport extends AbstractReportProvider
{
    public function __construct(
        private WorkforceReport $reports,
        private OperationalDigest $digest,
    ) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'schedule-coverage',
            title: 'Schedule coverage',
            description: 'Published assignments versus attended work, plus upcoming and unassigned shifts.',
            audience: ReportAudience::Tenant,
            category: 'Schedule',
            filters: ['department', 'location', 'arrangement'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $scope = $filters->scope;
        $schedule = $this->digest->schedule($scope);
        $arrangements = $this->reports->arrangementMix($scope->organization, $scope->from, $scope->to, $scope);

        if ($filters->arrangement !== null) {
            $arrangements = array_filter(
                $arrangements,
                fn ($total, $kind): bool => (string) $kind === $filters->arrangement,
                ARRAY_FILTER_USE_BOTH,
            );
        }

        $rows = [
            [
                'metric' => 'Scheduled assignments',
                'value' => $schedule['scheduled'],
            ],
            [
                'metric' => 'Attended (present or late)',
                'value' => $schedule['attended'],
            ],
            [
                'metric' => 'Coverage rate %',
                'value' => $schedule['rate'],
            ],
            [
                'metric' => 'Upcoming assignments (7 days)',
                'value' => $schedule['upcoming'],
            ],
            [
                'metric' => 'Unassigned published shifts (7 days)',
                'value' => $schedule['unassigned_shifts'],
            ],
        ];

        foreach ($arrangements as $kind => $total) {
            $label = WorkArrangementKind::tryFrom((string) $kind)?->label() ?? (string) $kind;
            $rows[] = [
                'metric' => 'Arrangement: '.$label,
                'value' => $total,
            ];
        }

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Scheduled', number_format($schedule['scheduled']), icon: 'calendar'),
                new ReportKpi('Attended', number_format($schedule['attended']), icon: 'check-circle', tone: 'success'),
                new ReportKpi('Coverage', $schedule['rate'] === null ? '—' : $schedule['rate'].'%', icon: 'chart', meter: $schedule['rate']),
                new ReportKpi('Unassigned shifts', number_format($schedule['unassigned_shifts']), icon: 'alert', tone: 'warning', emphasis: $schedule['unassigned_shifts'] > 0 ? 'warning' : null),
            ],
            columns: $this->columns([
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'value', 'label' => 'Value'],
            ]),
            rows: $rows,
            emptyMessage: 'No schedule coverage figures for this period.',
            findings: $schedule['unassigned_shifts'] > 0
                ? [number_format($schedule['unassigned_shifts']).' published shifts have no assignment in the next 7 days.']
                : [],
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        yield from $this->yieldRows($this->build($filters)->rowArrays());
    }
}
