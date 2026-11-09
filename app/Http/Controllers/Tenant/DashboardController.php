<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\LeaveStatus;
use App\Enums\ShiftStatus;
use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\LeaveRequest;
use App\Models\Location;
use App\Models\LoginEvent;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Workforce\WorkforceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Organisation overview. Every figure is an aggregate query (no table loads
 * into PHP) and reflects real records only; demo tenants additionally show
 * their labelled sample metrics.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $organization = $user->organization;
        $tz = $organization->timezone;

        $staffByStatus = StaffMember::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $staffByWorkType = StaffMember::query()
            ->where('status', StaffStatus::Active->value)
            ->select('work_type', DB::raw('count(*) as total'))
            ->groupBy('work_type')
            ->pluck('total', 'work_type');

        $counts = [
            'staff_total' => (int) $staffByStatus->sum(),
            'staff_active' => (int) ($staffByStatus[StaffStatus::Active->value] ?? 0),
            'staff_on_leave' => (int) ($staffByStatus[StaffStatus::OnLeave->value] ?? 0),
            'staff_inactive' => (int) (($staffByStatus[StaffStatus::Inactive->value] ?? 0) + ($staffByStatus[StaffStatus::Exited->value] ?? 0)),
            'users' => User::query()->where('organization_id', $organization->getKey())->count(),
            'users_suspended' => User::query()->where('organization_id', $organization->getKey())->where('status', 'suspended')->count(),
            'pending_invitations' => Invitation::query()->pending()->count(),
            'departments' => Department::query()->where('is_active', true)->count(),
            'locations' => Location::query()->where('is_active', true)->count(),
            'unlinked_staff' => StaffMember::query()->whereNull('user_id')->where('status', StaffStatus::Active->value)->count(),
        ];

        $workTypes = collect(WorkType::cases())->map(fn (WorkType $type) => [
            'label' => $type->label(),
            'value' => (int) ($staffByWorkType[$type->value] ?? 0),
        ]);

        $recentActivity = $user->can('audit.view')
            ? Activity::query()
                ->where('organization_id', $organization->getKey())
                ->with('causer:id,first_name,last_name')
                ->latest('id')
                ->limit(8)
                ->get()
            : collect();

        $recentSignIns = $user->can('audit.logins.view')
            ? LoginEvent::query()
                ->forOrganization($organization->getKey())
                ->with('user:id,first_name,last_name,email')
                ->latest('created_at')
                ->limit(6)
                ->get()
            : collect();

        $signInsToday = $user->can('audit.logins.view')
            ? LoginEvent::query()
                ->forOrganization($organization->getKey())
                ->where('event', 'login')
                ->where('created_at', '>=', now($tz)->startOfDay()->utc())
                ->count()
            : null;

        return view('tenant.dashboard', [
            'user' => $user,
            'organization' => $organization,
            'counts' => $counts,
            'workTypes' => $workTypes,
            'recentActivity' => $recentActivity,
            'recentSignIns' => $recentSignIns,
            'signInsToday' => $signInsToday,
            'demoMetrics' => $organization->isDemo() ? ($organization->settings['sample_metrics'] ?? null) : null,
            'attendanceToday' => $user->can('attendance.view') ? app(WorkforceReport::class)->day($organization) : null,
            'upcomingAssignments' => $this->upcomingAssignments($user, $tz),
            'pendingLeave' => $this->pendingLeave($user),
        ]);
    }

    /**
     * Next five assignments by work date. Managers see the organisation list.
     * Everyone else sees only their own published assignments.
     */
    private function upcomingAssignments(User $user, string $tz): Collection
    {
        if (! $user->can('schedules.view')) {
            return collect();
        }

        $today = now($tz)->toDateString();

        return ShiftAssignment::query()
            ->with([
                'shift:id,name,starts_at,ends_at,status',
                'staffMember:id,first_name,last_name',
            ])
            ->where('work_date', '>=', $today)
            ->when(! $user->can('schedules.manage'), function ($query) use ($user): void {
                $query->where('staff_member_id', $user->staffMember?->getKey() ?? 0)
                    ->whereHas('shift', fn ($shift) => $shift->where('status', ShiftStatus::Published->value));
            })
            ->orderBy('work_date')
            ->orderBy('id')
            ->limit(5)
            ->get();
    }

    /**
     * Leave still waiting for a decision, soonest start date first.
     */
    private function pendingLeave(User $user): Collection
    {
        if (! $user->can('leave.approve')) {
            return collect();
        }

        return LeaveRequest::query()
            ->with([
                'staffMember:id,first_name,last_name',
                'leaveType:id,name',
            ])
            ->where('status', LeaveStatus::Submitted->value)
            ->orderBy('starts_on')
            ->orderBy('id')
            ->limit(5)
            ->get();
    }
}
