<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\RecordFieldLocationRequest;
use App\Http\Requests\Workforce\StoreFieldAssignmentRequest;
use App\Models\FieldActivity;
use App\Models\FieldAssignment;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\WorkforcePolicy;
use App\Workforce\AttendanceException;
use App\Workforce\FieldService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.view') || $request->user()->can('attendance.record'), 403);

        $user = $request->user();
        $own = $user->staffMember;
        $policy = WorkforcePolicy::resolve($user->organization);

        $assignments = FieldAssignment::query()
            ->with(['staffMember:id,uuid,first_name,last_name', 'location:id,uuid,name,latitude,longitude'])
            ->when(! $user->can('attendance.view'), fn ($query) => $query->where('staff_member_id', $own?->getKey() ?? 0))
            ->latest('starts_on')
            ->paginate(20);

        $activities = FieldActivity::query()
            ->with(['staffMember:id,first_name,last_name', 'assignment.location:id,name'])
            ->whereNull('ended_at')
            ->when(! $user->can('attendance.view'), fn ($query) => $query->where('staff_member_id', $own?->getKey() ?? 0))
            ->latest('started_at')
            ->limit(50)
            ->get();

        return view('tenant.field.index', [
            'assignments' => $assignments,
            'activities' => $activities,
            'delayMinutes' => $policy->field_delay_minutes,
            'canManage' => $user->can('attendance.manage'),
            'staff' => $user->can('attendance.manage') && strlen($person = trim((string) $request->query('person', ''))) >= 2
                ? StaffMember::query()->search($person)->orderBy('last_name')->limit(30)->get(['uuid', 'first_name', 'last_name'])
                : collect(),
            'locations' => Location::query()
                ->where('is_active', true)
                ->when(strlen($place = trim((string) $request->query('place', ''))) >= 2, fn ($query) => $query->where('name', 'like', '%'.$place.'%'))
                ->orderBy('name')
                ->limit(60)
                ->get(['uuid', 'name']),
            'personFind' => trim((string) $request->query('person', '')),
            'placeFind' => trim((string) $request->query('place', '')),
            'timezone' => $user->organization->timezone,
        ]);
    }

    public function store(StoreFieldAssignmentRequest $request, FieldService $field): RedirectResponse
    {
        $input = $request->validated();

        $field->assign(
            $request->user(),
            StaffMember::query()->where('uuid', $input['staff_uuid'])->firstOrFail(),
            Location::query()->where('uuid', $input['location_uuid'])->firstOrFail(),
            $input['title'],
            $input['instructions'] ?? null,
            now()->parse($input['starts_on']),
            now()->parse($input['ends_on']),
        );

        return back()->with('status', 'Field assignment saved.');
    }

    public function start(RecordFieldLocationRequest $request, FieldAssignment $assignment, FieldService $field): RedirectResponse
    {
        try {
            $field->start($request->user(), $assignment, $request->validated());
        } catch (AttendanceException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()]);
        }

        return back()->with('status', 'Field activity started.');
    }

    public function finish(RecordFieldLocationRequest $request, FieldActivity $activity, FieldService $field): RedirectResponse
    {
        try {
            $field->finish($request->user(), $activity, $request->validated());
        } catch (AttendanceException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()]);
        }

        return back()->with('status', 'Field activity completed.');
    }
}
