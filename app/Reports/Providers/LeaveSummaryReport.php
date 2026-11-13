<?php

namespace App\Reports\Providers;

use App\Analytics\OperationalDigest;
use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\StaffMember;
use App\Reports\ReportAudience;
use App\Reports\ReportDefinition;
use App\Reports\ReportFilters;
use App\Reports\ReportFormat;
use App\Reports\ReportKpi;
use App\Reports\ReportResult;
use App\Support\CalendarDay;
use Generator;

final class LeaveSummaryReport extends AbstractReportProvider
{
    public function __construct(private OperationalDigest $digest) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'leave-summary',
            title: 'Leave summary',
            description: 'Leave decisions, utilisation, pending requests, and upcoming approved leave.',
            audience: ReportAudience::Tenant,
            category: 'Leave',
            filters: ['department', 'person', 'leave_type', 'leave_status'],
            formats: [ReportFormat::Csv, ReportFormat::Xlsx, ReportFormat::Pdf],
            paginated: true,
        );
    }

    public function build(ReportFilters $filters, int $page = 1): ReportResult
    {
        $scope = $filters->scope;
        $leave = $this->digest->leave($scope, $filters->leaveTypeId);
        $query = $this->requestQuery($filters);
        $paginator = $query->paginate(50, ['*'], 'page', $page)->withQueryString();
        $paginator->setCollection(collect($paginator->items())->map(
            fn (LeaveRequest $request): array => $this->map($request),
        ));

        return $this->result(
            filters: $filters,
            kpis: [
                new ReportKpi('Pending', number_format($leave['pending']), icon: 'clock', tone: 'warning'),
                new ReportKpi('Utilisation', $leave['utilisation'] === null ? '—' : $leave['utilisation'].'%', hint: number_format($leave['used_days']).' of '.number_format($leave['entitled_days']).' entitled days used', icon: 'chart', meter: $leave['utilisation']),
                new ReportKpi('Upcoming approved', number_format($leave['upcoming_approved']), icon: 'calendar', tone: 'accent'),
                new ReportKpi('Requests in range', number_format($paginator->total()), icon: 'document-check'),
            ],
            columns: $this->columns([
                ['key' => 'person', 'label' => 'Person'],
                ['key' => 'staff_number', 'label' => 'Staff number'],
                ['key' => 'type', 'label' => 'Leave type'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'starts_on', 'label' => 'Starts'],
                ['key' => 'ends_on', 'label' => 'Ends'],
                ['key' => 'days', 'label' => 'Days'],
            ]),
            rows: $paginator,
            emptyMessage: 'No leave requests match these filters.',
            findings: [
                'Decision counts use requests whose start falls in the selected range.',
                'Pending includes submitted requests even when they start outside the chart range.',
            ],
        );
    }

    public function exportRows(ReportFilters $filters): Generator
    {
        foreach ($this->requestQuery($filters)->lazy(200) as $request) {
            yield $this->map($request);
        }
    }

    public function estimateRows(ReportFilters $filters): int
    {
        return (int) $this->requestQuery($filters)->count();
    }

    private function requestQuery(ReportFilters $filters)
    {
        $scope = $filters->scope;

        return LeaveRequest::query()
            ->with(['staffMember:id,staff_number,first_name,last_name', 'leaveType:id,name'])
            ->where('organization_id', $scope->organization->getKey())
            ->where('starts_on', '>=', $scope->from->toDateString())
            ->where('starts_on', '<', CalendarDay::following($scope->to->toDateString()))
            ->when($scope->staffMemberId !== null, fn ($query) => $query->where('staff_member_id', $scope->staffMemberId))
            ->when($scope->departmentIds !== null, function ($query) use ($scope): void {
                $query->whereIn('staff_member_id', StaffMember::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds)
                    ->select('id'));
            })
            ->when($filters->leaveTypeId !== null, fn ($query) => $query->where('leave_type_id', $filters->leaveTypeId))
            ->when($filters->leaveStatus !== null, fn ($query) => $query->where('status', $filters->leaveStatus))
            ->orderBy('starts_on')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function map(LeaveRequest $request): array
    {
        return [
            'person' => $request->staffMember?->name,
            'staff_number' => $request->staffMember?->staff_number,
            'type' => $request->leaveType?->name,
            'status' => $request->status instanceof LeaveStatus ? $request->status->label() : (string) $request->status,
            'starts_on' => $request->starts_on?->toDateString(),
            'ends_on' => $request->ends_on?->toDateString(),
            'days' => $request->day_count,
        ];
    }
}
