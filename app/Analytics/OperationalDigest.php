<?php

namespace App\Analytics;

use App\Enums\LeaveStatus;
use App\Enums\ShiftStatus;
use App\Models\DatabaseNotification;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\WorkforceNoticeDelivery;
use App\Support\CalendarDay;
use App\Workforce\WorkforceReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Schedule, leave, and notification aggregates for an already authorised scope.
 * Attendance figures stay in WorkforceReport so dashboards and the analytics
 * page share one formula.
 */
final class OperationalDigest
{
    public function __construct(
        private WorkforceReport $attendance,
        private AnalyticsCache $cache,
    ) {}

    /**
     * @return array{present: int, late: int, absent: int, on_leave: int, incomplete: int, excused: int, active_staff: int, absent_basis: string}
     */
    public function today(AnalyticsScope $scope): array
    {
        $today = now($scope->organization->timezone ?: 'UTC')->toDateString();

        return $this->cache->remember($scope->withDates(
            Carbon::parse($today),
            Carbon::parse($today),
        ), 'attendance.today', fn (): array => $this->attendance->day($scope->organization, $today, $scope));
    }

    /**
     * @return array{scheduled: int, attended: int, rate: int|null, upcoming: int, unassigned_shifts: int}
     */
    public function schedule(AnalyticsScope $scope): array
    {
        return $this->cache->remember($scope, 'schedule', function () use ($scope): array {
            $adherence = $this->attendance->scheduleAdherence($scope->organization, $scope->from, $scope->to, $scope);
            $timezone = $scope->organization->timezone ?: 'UTC';
            $start = now($timezone)->toDateString();
            $end = now($timezone)->addDays(6)->toDateString();

            $upcoming = $this->publishedAssignments($scope)
                ->where('work_date', '>=', $start)
                ->where('work_date', '<', CalendarDay::following($end))
                ->count();

            $unassigned = Shift::query()
                ->where('organization_id', $scope->organization->getKey())
                ->where('status', ShiftStatus::Published->value)
                ->where('effective_from', '<', CalendarDay::following($end))
                ->where(function ($query) use ($start): void {
                    $query->whereNull('effective_until')->orWhere('effective_until', '>=', $start);
                })
                ->when($scope->departmentIds !== null, function ($query) use ($scope): void {
                    $query->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds);
                })
                ->whereDoesntHave('assignments', function ($query) use ($start, $end): void {
                    $query->where('work_date', '>=', $start)->where('work_date', '<', CalendarDay::following($end));
                })
                ->count();

            $scheduled = (int) $adherence['scheduled'];

            return [
                'scheduled' => $scheduled,
                'attended' => (int) $adherence['attended'],
                'rate' => $scheduled > 0 ? (int) round($adherence['attended'] / $scheduled * 100) : null,
                'upcoming' => $upcoming,
                'unassigned_shifts' => $unassigned,
            ];
        });
    }

    /**
     * Utilisation uses the open leave-year balance: used days divided by
     * entitled plus adjustments. A zero entitlement is excluded from the rate.
     *
     * @return array{decisions: array<string, int>, pending: int, utilisation: int|null, entitled_days: int, used_days: int, upcoming_approved: int}
     */
    public function leave(AnalyticsScope $scope): array
    {
        return $this->cache->remember($scope, 'leave', function () use ($scope): array {
            $decisions = $this->attendance->leaveDecisions($scope->organization, $scope->from, $scope->to, $scope);
            $today = now($scope->organization->timezone ?: 'UTC')->toDateString();
            $soon = now($scope->organization->timezone ?: 'UTC')->addDays(14)->toDateString();

            $pending = $this->leaveQuery($scope)->where('status', LeaveStatus::Submitted->value)->count();

            $balances = LeaveBalance::query()
                ->where('organization_id', $scope->organization->getKey())
                ->where('period_start', '<', CalendarDay::following($today))
                ->where('period_end', '>=', $today)
                ->when($scope->staffMemberId !== null, fn ($query) => $query->where('staff_member_id', $scope->staffMemberId))
                ->when($scope->departmentIds !== null, function ($query) use ($scope): void {
                    $query->whereIn('staff_member_id', StaffMember::query()
                        ->where('organization_id', $scope->organization->getKey())
                        ->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds)
                        ->select('id'));
                })
                ->selectRaw('coalesce(sum(case when entitled_days + adjusted_days > 0 then used_days else 0 end), 0) as used_days, coalesce(sum(case when entitled_days + adjusted_days > 0 then entitled_days + adjusted_days else 0 end), 0) as entitled_days')
                ->first();

            $entitled = (int) ($balances->entitled_days ?? 0);
            $used = (int) ($balances->used_days ?? 0);

            $upcoming = $this->leaveQuery($scope)
                ->where('status', LeaveStatus::Approved->value)
                ->where('ends_on', '>=', $today)
                ->where('starts_on', '<', CalendarDay::following($soon))
                ->count();

            return [
                'decisions' => $decisions,
                'pending' => $pending,
                'utilisation' => $entitled > 0 ? (int) round($used / $entitled * 100) : null,
                'entitled_days' => $entitled,
                'used_days' => $used,
                'upcoming_approved' => $upcoming,
            ];
        });
    }

    /**
     * Delivery status and read status are separate. Department and personal
     * scopes do not receive organisation-wide delivery totals.
     *
     * @return array{deliveries: array<string, int>|null, unread: int, read: int|null}
     */
    public function notifications(AnalyticsScope $scope): array
    {
        return $this->cache->remember($scope, 'notifications', function () use ($scope): array {
            if ($scope->perspective !== AnalyticsScope::ORGANISATION) {
                return [
                    'deliveries' => null,
                    'unread' => $this->viewerUnread($scope),
                    'read' => null,
                ];
            }

            $deliveries = [];
            foreach (
                WorkforceNoticeDelivery::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->select('status')
                    ->selectRaw('count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status') as $status => $total
            ) {
                $deliveries[$status === null || $status === '' ? 'unspecified' : (string) $status] = (int) $total;
            }

            $reads = DatabaseNotification::query()
                ->where('organization_id', $scope->organization->getKey())
                ->selectRaw('sum(case when read_at is null then 1 else 0 end) as unread, sum(case when read_at is null then 0 else 1 end) as read_count')
                ->first();

            return [
                'deliveries' => $deliveries,
                'unread' => (int) ($reads->unread ?? 0),
                'read' => (int) ($reads->read_count ?? 0),
            ];
        });
    }

    /**
     * @return array<string, int>
     */
    public function recordCounts(AnalyticsScope $scope, Carbon $from, Carbon $to): array
    {
        $window = $scope->withDates($from, $to);

        return $this->cache->remember($window, 'attendance.record-counts', function () use ($scope, $from, $to): array {
            $mix = $this->attendance->statusMix($scope->organization, $from, $to, $scope);
            $counts = [];
            foreach ($mix as $row) {
                $counts[$row['key']] = (int) $row['total'];
            }

            return $counts;
        });
    }

    private function publishedAssignments(AnalyticsScope $scope): Builder
    {
        return ShiftAssignment::query()
            ->where('organization_id', $scope->organization->getKey())
            ->whereHas('shift', fn ($query) => $query->where('status', ShiftStatus::Published->value))
            ->when($scope->staffMemberId !== null, fn ($query) => $query->where('staff_member_id', $scope->staffMemberId))
            ->when($scope->departmentIds !== null, function ($query) use ($scope): void {
                $query->whereIn('staff_member_id', StaffMember::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds)
                    ->select('id'));
            })
            ->when($scope->locationId !== null, fn ($query) => $query->where('location_id', $scope->locationId));
    }

    private function leaveQuery(AnalyticsScope $scope): Builder
    {
        return LeaveRequest::query()
            ->where('organization_id', $scope->organization->getKey())
            ->when($scope->staffMemberId !== null, fn ($query) => $query->where('staff_member_id', $scope->staffMemberId))
            ->when($scope->departmentIds !== null, function ($query) use ($scope): void {
                $query->whereIn('staff_member_id', StaffMember::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds)
                    ->select('id'));
            });
    }

    private function viewerUnread(AnalyticsScope $scope): int
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $scope->viewer->getMorphClass())
            ->where('notifiable_id', $scope->viewer->getKey())
            ->where('organization_id', $scope->organization->getKey())
            ->whereNull('read_at')
            ->count();
    }
}
