<?php

namespace App\Analytics;

use App\Enums\LeaveStatus;
use App\Enums\ShiftStatus;
use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\LoginEvent;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Support\CalendarDay;
use App\Workforce\Communication\Inbox;
use App\Workforce\LeavePlanner;
use App\Workforce\WorkforceReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One composer for every tenant dashboard. The perspective decides which
 * queries run. A personal workspace never loads organisation aggregates.
 */
final class DashboardComposer
{
    public function __construct(
        private WorkforceReport $attendance,
        private OperationalDigest $digest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function compose(User $user): array
    {
        $organization = $user->organization;
        $timezone = $organization->timezone ?: 'UTC';
        $today = now($timezone)->toDateString();
        $scope = AnalyticsScope::forUser($user, now($timezone)->subDays(13)->toDateString(), $today);
        $personal = $scope->perspective === AnalyticsScope::PERSONAL;

        $attendanceToday = $user->can('attendance.view') || $personal
            ? $this->digest->today($scope)
            : null;

        $payload = [
            'user' => $user,
            'organization' => $organization,
            'perspective' => $scope->perspective,
            'scopeLabel' => $scope->label(),
            'counts' => $personal ? null : $this->organisationCounts($user, $scope),
            'workTypes' => $personal ? null : $this->workTypes($scope),
            'population' => $personal || ! $organization->isEducational() ? null : $this->population($scope),
            'attendanceToday' => $attendanceToday,
            'trend' => ($user->can('attendance.view') || $user->can('analytics.view')) && ! $personal
                ? $this->paintTrend($this->attendance->trend($organization, $scope->from, $scope->to, $scope))
                : [],
            'trendFrom' => $scope->from->toDateString(),
            'trendTo' => $scope->to->toDateString(),
            'trendSeries' => $this->trendSeries(),
            'schedule' => $personal ? null : ($user->can('schedules.view') ? $this->digest->schedule($scope) : null),
            'leaveSummary' => $personal ? null : ($user->can('leave.approve') || $user->can('analytics.view') ? $this->digest->leave($scope) : null),
            'upcomingAssignments' => $this->upcomingAssignments($user, $scope, $timezone),
            'pendingLeave' => $this->pendingLeave($user, $scope),
            'recentNotifications' => Inbox::recent($user),
            'unreadNotifications' => Inbox::unreadCount($user),
            'signInsToday' => $this->signInsToday($user, $timezone),
            'personal' => $personal ? $this->personal($user, $scope, $timezone) : null,
        ];

        return $payload;
    }

    /**
     * @return array<string, int>
     */
    private function organisationCounts(User $user, AnalyticsScope $scope): array
    {
        $staff = StaffMember::query()->where('organization_id', $scope->organization->getKey());
        if ($scope->departmentIds !== null) {
            $staff->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds);
        }

        $byStatus = (clone $staff)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $organisationWide = $scope->perspective === AnalyticsScope::ORGANISATION;

        return [
            'staff_total' => (int) $byStatus->sum(),
            'staff_active' => (int) ($byStatus[StaffStatus::Active->value] ?? 0),
            'staff_on_leave' => (int) ($byStatus[StaffStatus::OnLeave->value] ?? 0),
            'staff_inactive' => (int) (($byStatus[StaffStatus::Inactive->value] ?? 0) + ($byStatus[StaffStatus::Exited->value] ?? 0)),
            'users' => $organisationWide && $user->can('users.view')
                ? User::query()->where('organization_id', $scope->organization->getKey())->count()
                : 0,
            'users_suspended' => $organisationWide && $user->can('users.view')
                ? User::query()->where('organization_id', $scope->organization->getKey())->where('status', 'suspended')->count()
                : 0,
            'pending_invitations' => $organisationWide && $user->can('users.invite')
                ? Invitation::query()->pending()->count()
                : 0,
            'departments' => $organisationWide
                ? Department::query()->where('is_active', true)->count()
                : count($scope->departmentIds ?? []),
            'locations' => $organisationWide
                ? Location::query()->where('is_active', true)->count()
                : 0,
            'unlinked_staff' => (int) (clone $staff)->whereNull('user_id')->where('status', StaffStatus::Active->value)->count(),
            'show_accounts' => $organisationWide && $user->can('users.view'),
            'show_structure' => $organisationWide,
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function workTypes(AnalyticsScope $scope): array
    {
        $counts = StaffMember::query()
            ->where('organization_id', $scope->organization->getKey())
            ->where('status', StaffStatus::Active->value)
            ->when($scope->departmentIds !== null, fn ($query) => $query->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds))
            ->select('work_type', DB::raw('count(*) as total'))
            ->groupBy('work_type')
            ->pluck('total', 'work_type');

        return array_map(fn (WorkType $type): array => [
            'label' => $type->label(),
            'value' => (int) ($counts[$type->value] ?? 0),
        ], WorkType::cases());
    }

    /**
     * @return array{students: int, teaching: int, other: int}
     */
    private function population(AnalyticsScope $scope): array
    {
        $base = StaffMember::query()
            ->where('organization_id', $scope->organization->getKey())
            ->where('status', StaffStatus::Active->value)
            ->when($scope->departmentIds !== null, fn ($query) => $query->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds));

        $students = (clone $base)->where(function ($query): void {
            $query->where('employment_type', 'student')->orWhere('job_title', 'Student');
        })->count();
        $teaching = (clone $base)->where('job_title', 'Lecturer')->count();
        $active = (clone $base)->count();

        return [
            'students' => $students,
            'teaching' => $teaching,
            'other' => max(0, $active - $students - $teaching),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $trend
     * @return list<array<string, mixed>>
     */
    private function paintTrend(array $trend): array
    {
        return $trend;
    }

    /**
     * @return list<array{key: string, label: string, ink: string}>
     */
    public function trendSeries(): array
    {
        return array_map(fn (string $key, string $label): array => [
            'key' => $key,
            'label' => $label,
            'ink' => ChartPalette::color($key),
        ], ['present', 'late', 'on_leave', 'other'], ['Present', 'Late', 'On leave', 'Other']);
    }

    private function upcomingAssignments(User $user, AnalyticsScope $scope, string $timezone): Collection
    {
        if (! $user->can('schedules.view')) {
            return collect();
        }

        $today = now($timezone)->toDateString();

        return ShiftAssignment::query()
            ->with([
                'shift:id,name,starts_at,ends_at,status',
                'staffMember:id,first_name,last_name',
                'arrangement:id,name',
            ])
            ->where('work_date', '>=', $today)
            ->when($scope->perspective === AnalyticsScope::PERSONAL || ! $user->can('schedules.manage'), function ($query) use ($user): void {
                $query->where('staff_member_id', $user->staffMember?->getKey() ?? 0)
                    ->whereHas('shift', fn ($shift) => $shift->where('status', ShiftStatus::Published->value));
            })
            ->when($scope->departmentIds !== null && $user->can('schedules.manage'), function ($query) use ($scope): void {
                $query->whereIn('staff_member_id', StaffMember::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds)
                    ->select('id'));
            })
            ->orderBy('work_date')
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    private function pendingLeave(User $user, AnalyticsScope $scope): Collection
    {
        if (! $user->can('leave.approve') || $scope->perspective === AnalyticsScope::PERSONAL) {
            return collect();
        }

        return LeaveRequest::query()
            ->with([
                'staffMember:id,first_name,last_name',
                'leaveType:id,name',
            ])
            ->where('status', LeaveStatus::Submitted->value)
            ->when($scope->departmentIds !== null, function ($query) use ($scope): void {
                $query->whereIn('staff_member_id', StaffMember::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->whereIn('department_id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds)
                    ->select('id'));
            })
            ->orderBy('starts_on')
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    private function signInsToday(User $user, string $timezone): ?int
    {
        if (! $user->can('audit.logins.view')) {
            return null;
        }

        return LoginEvent::query()
            ->forOrganization($user->organization_id)
            ->where('event', 'login')
            ->where('created_at', '>=', now($timezone)->startOfDay()->utc())
            ->count();
    }

    /**
     * Same available-day figure as the leave page: a stored open balance when one
     * exists, otherwise the planner entitlement for the open period.
     *
     * @return list<array{name: string, available: int, requires_balance: bool}>
     */
    private function personalBalances(StaffMember $staff, string $today): array
    {
        $stored = LeaveBalance::query()
            ->where('staff_member_id', $staff->getKey())
            ->where('period_start', '<', CalendarDay::following($today))
            ->where('period_end', '>=', $today)
            ->get()
            ->keyBy('leave_type_id');
        $planner = app(LeavePlanner::class);
        $lines = [];

        foreach (LeaveType::query()->where('organization_id', $staff->organization_id)->where('is_active', true)->orderBy('name')->get() as $type) {
            $row = $stored->get($type->getKey());
            $requiresBalance = (bool) $type->requires_balance;

            if ($row instanceof LeaveBalance) {
                $lines[] = ['name' => $type->name, 'available' => $row->available(), 'requires_balance' => $requiresBalance];

                continue;
            }

            $policy = $planner->policyOn($type, $today);
            $periodStart = $policy === null ? substr($today, 0, 4).'-01-01' : $planner->period($policy, $today)[0];
            $lines[] = [
                'name' => $type->name,
                'available' => $planner->entitledDays($staff, $type, $policy, $periodStart),
                'requires_balance' => $requiresBalance,
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function personal(User $user, AnalyticsScope $scope, string $timezone): array
    {
        $staffId = $user->staffMember?->getKey();
        $today = now($timezone)->toDateString();

        $balances = $staffId === null
            ? []
            : $this->personalBalances($user->staffMember, $today);

        $requests = $staffId === null
            ? collect()
            : LeaveRequest::query()
                ->with('leaveType:id,name')
                ->where('staff_member_id', $staffId)
                ->orderByDesc('starts_on')
                ->limit(5)
                ->get();
        $openRequests = $staffId === null
            ? 0
            : LeaveRequest::query()
                ->where('staff_member_id', $staffId)
                ->where('status', LeaveStatus::Submitted->value)
                ->count();

        $history = $staffId === null
            ? collect()
            : AttendanceRecord::query()
                ->where('staff_member_id', $staffId)
                ->orderByDesc('work_date')
                ->orderByDesc('id')
                ->limit(7)
                ->get(['id', 'work_date', 'status', 'check_in_at', 'check_out_at']);

        return [
            'linked' => $staffId !== null,
            'today' => $this->digest->today($scope),
            'balances' => $balances,
            'requests' => $requests,
            'open_requests' => $openRequests,
            'history' => $history,
            'next' => $this->upcomingAssignments($user, $scope, $timezone)->first(),
        ];
    }
}
