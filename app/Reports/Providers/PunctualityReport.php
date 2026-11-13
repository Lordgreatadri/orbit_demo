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
use App\Workforce\WorkforceReport;
use Generator;

final class PunctualityReport extends AbstractReportProvider
{
    public function __construct(private WorkforceReport $reports) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'punctuality',
            title: 'Punctuality and late arrivals',
            description: 'Late attendance records and accumulated late minutes for the selected period.',
            audience: ReportAudience::Tenant,
            category: 'Attendance',
            filters: ['department', 'location', 'person'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx],
            paginated: true,
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $summary = $this->reports->punctuality(
            $filters->scope->organization,
            $filters->scope->from,
            $filters->scope->to,
            $filters->scope,
        );
        $timezone = $filters->scope->organization->timezone ?: 'UTC';
        $paginator = $this->lateQuery($filters)
            ->paginate(50, ['*'], 'page', $page)
            ->withQueryString();
        $paginator->setCollection(collect($paginator->items())->map(
            fn (AttendanceRecord $record): array => $this->map($record, $timezone),
        ));

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Late people', number_format($summary['people']), icon: 'users', tone: 'warning'),
                new ReportKpi('Late records', number_format($summary['records']), icon: 'clock', tone: 'warning'),
                new ReportKpi('Late minutes', number_format($summary['late_minutes']), icon: 'chart'),
            ],
            columns: $this->columns([
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff_number', 'label' => 'Staff number'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'department', 'label' => 'Department'],
                ['key' => 'check_in', 'label' => 'Check in'],
                ['key' => 'late_minutes', 'label' => 'Late minutes'],
            ]),
            rows: $paginator,
            emptyMessage: 'No late arrivals in this period.',
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        $timezone = $filters->scope->organization->timezone ?: 'UTC';

        foreach ($this->lateQuery($filters)->lazy(200) as $record) {
            yield $this->map($record, $timezone);
        }
    }

    public function estimateRows(ReportFilters $filters): int
    {
        return (int) $this->lateQuery($filters)->count();
    }

    private function lateQuery(ReportFilters $filters)
    {
        $scope = $filters->scope;

        return AttendanceRecord::query()
            ->with(['staffMember:id,staff_number,first_name,last_name', 'department:id,name'])
            ->where('organization_id', $scope->organization->getKey())
            ->where('work_date', '>=', $scope->from->toDateString())
            ->where('work_date', '<', CalendarDay::following($scope->to->toDateString()))
            ->where('status', AttendanceStatus::Late->value)
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
            'check_in' => $record->check_in_at?->timezone($timezone)->format('Y-m-d H:i'),
            'late_minutes' => $record->late_minutes,
        ];
    }
}
