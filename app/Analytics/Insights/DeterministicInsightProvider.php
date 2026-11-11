<?php

namespace App\Analytics\Insights;

use App\Analytics\AnalyticsScope;
use App\Analytics\Insight;
use App\Analytics\OperationalDigest;
use App\Contracts\OperationalInsightProvider;
use App\Enums\AttendanceStatus;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\StaffMember;

/**
 * Thresholds are fixed and the wording quotes the figures that crossed them.
 * No external model is called. Personal workspaces do not receive organisation insights.
 */
final class DeterministicInsightProvider implements OperationalInsightProvider
{
    public function __construct(private OperationalDigest $digest) {}

    public function forScope(AnalyticsScope $scope, ?array $today = null): array
    {
        if ($scope->perspective === AnalyticsScope::PERSONAL) {
            return [];
        }

        $insights = [];
        $today ??= $this->digest->today($scope);
        $timezone = $scope->organization->timezone ?: 'UTC';
        $todayLabel = now($timezone)->toDateString();

        if ($today['active_staff'] >= 5 && $today['absent_basis'] === 'derived') {
            $share = $today['absent'] / $today['active_staff'];
            if ($share >= 0.25) {
                $insights[] = new Insight(
                    'coverage_gap',
                    'Attendance coverage is thin today',
                    $today['absent'].' of '.$today['active_staff'].' active people have no covering attendance record today. Covering means present, late, on leave, incomplete, or excused.',
                    $todayLabel,
                    'Derived absent people ÷ active people',
                    'Open today\'s attendance register and confirm who still needs a record.',
                    route('org.attendance.index'),
                    'action',
                );
            }
        }

        $length = (int) $scope->from->diffInDays($scope->to) + 1;
        $previousEnd = $scope->from->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($length - 1);
        $current = $this->digest->recordCounts($scope, $scope->from, $scope->to);
        $previous = $this->digest->recordCounts($scope, $previousStart, $previousEnd);
        $currentAttended = (int) ($current[AttendanceStatus::Present->value] ?? 0) + (int) ($current[AttendanceStatus::Late->value] ?? 0);
        $previousAttended = (int) ($previous[AttendanceStatus::Present->value] ?? 0) + (int) ($previous[AttendanceStatus::Late->value] ?? 0);

        if ($previousAttended >= 5 && $currentAttended <= (int) floor($previousAttended * 0.8)) {
            $insights[] = new Insight(
                'attendance_decline',
                'Present and late records are lower than the previous period',
                'This window has '.$currentAttended.' present or late attendance records. The previous '.$length.' days had '.$previousAttended.'.',
                $scope->from->toDateString().' to '.$scope->to->toDateString(),
                'Count of present and late attendance records',
                'Compare the attendance register for the two periods before treating the drop as absence.',
                route('org.analytics.index', ['from' => $scope->from->toDateString(), 'to' => $scope->to->toDateString()]),
            );
        }

        $currentIncomplete = (int) ($current[AttendanceStatus::Incomplete->value] ?? 0);
        $previousIncomplete = (int) ($previous[AttendanceStatus::Incomplete->value] ?? 0);
        if ($currentIncomplete >= 3 && $currentIncomplete > $previousIncomplete) {
            $insights[] = new Insight(
                'incomplete_rise',
                'Incomplete attendance records are above the previous period',
                'This window has '.$currentIncomplete.' incomplete records. The previous '.$length.' days had '.$previousIncomplete.'.',
                $scope->from->toDateString().' to '.$scope->to->toDateString(),
                'Count of incomplete attendance records',
                'Review open check-ins that have no check-out.',
                route('org.attendance.index'),
                'action',
            );
        }

        $schedule = $this->digest->schedule($scope);
        if ($schedule['unassigned_shifts'] > 0) {
            $insights[] = new Insight(
                'unassigned_shifts',
                'Published shifts have no assignment in the next 7 days',
                $schedule['unassigned_shifts'].' published shift'.($schedule['unassigned_shifts'] === 1 ? '' : 's').' cover the next 7 days and have no assignment in that window. Draft shifts are not included.',
                $todayLabel.' to '.now($timezone)->addDays(6)->toDateString(),
                'Published shifts with zero assignments in the next 7 days',
                'Open schedules and assign the uncovered shift, or leave it unpublished if it should not run.',
                route('org.schedules.index'),
            );
        }

        $concentration = $this->leaveConcentration($scope);
        if ($concentration !== null) {
            $insights[] = $concentration;
        }

        return array_slice($insights, 0, 4);
    }

    private function leaveConcentration(AnalyticsScope $scope): ?Insight
    {
        $timezone = $scope->organization->timezone ?: 'UTC';
        $start = now($timezone)->toDateString();
        $end = now($timezone)->addDays(13)->toDateString();

        $departments = Department::query()
            ->where('organization_id', $scope->organization->getKey())
            ->when($scope->departmentIds !== null, fn ($query) => $query->whereIn('id', $scope->departmentIds === [] ? [-1] : $scope->departmentIds))
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name']);

        foreach ($departments as $department) {
            $active = StaffMember::query()
                ->where('organization_id', $scope->organization->getKey())
                ->where('department_id', $department->getKey())
                ->where('status', 'active')
                ->count();

            if ($active < 5) {
                continue;
            }

            $away = LeaveRequest::query()
                ->where('organization_id', $scope->organization->getKey())
                ->where('status', 'approved')
                ->where('starts_on', '<=', $end)
                ->where('ends_on', '>=', $start)
                ->whereIn('staff_member_id', StaffMember::query()
                    ->where('organization_id', $scope->organization->getKey())
                    ->where('department_id', $department->getKey())
                    ->select('id'))
                ->distinct()
                ->count('staff_member_id');

            if ($away / $active < 0.4) {
                continue;
            }

            return new Insight(
                'leave_concentration',
                $department->name.' has a high share of approved leave',
                $away.' of '.$active.' active people in '.$department->name.' have approved leave overlapping the next 14 days.',
                $start.' to '.$end,
                'People with approved leave ÷ active people in the department',
                'Check schedule coverage for '.$department->name.' before approving more leave in this window.',
                route('org.leave.index'),
            );
        }

        return null;
    }
}
