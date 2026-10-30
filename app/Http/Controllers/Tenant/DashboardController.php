<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\LoginEvent;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
            'setupSteps' => $this->setupSteps($counts, $organization),
        ]);
    }

    /**
     * Onboarding checklist derived from real state.
     *
     * @param  array<string, int>  $counts
     * @return list<array{label: string, done: bool, route: ?string, hint: string}>
     */
    private function setupSteps(array $counts, $organization): array
    {
        return [
            ['label' => 'Confirm organisation details', 'done' => $organization->isActive() && $organization->contact_phone !== null, 'route' => route('org.organizations.edit', $organization), 'hint' => 'Name, type, time zone, and contact details.'],
            ['label' => 'Create departments', 'done' => $counts['departments'] > 0, 'route' => route('org.departments.index'), 'hint' => 'Group staff the way your organisation is structured.'],
            ['label' => 'Add approved locations', 'done' => $counts['locations'] > 0, 'route' => route('org.locations.index'), 'hint' => 'Offices and field areas where attendance may be recorded.'],
            ['label' => 'Build the staff register', 'done' => $counts['staff_total'] > 0, 'route' => route('org.staff.index'), 'hint' => 'People records with staff number, department, and work type.'],
            ['label' => 'Invite user accounts', 'done' => $counts['users'] > 1, 'route' => route('org.users.index'), 'hint' => 'Give colleagues a login and link it to their staff record.'],
        ];
    }
}
