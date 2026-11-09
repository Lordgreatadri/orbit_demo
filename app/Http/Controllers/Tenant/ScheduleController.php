<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\AssignShiftRequest;
use App\Http\Requests\Workforce\StoreShiftRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Workforce\AttendanceException;
use App\Workforce\ScheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('schedules.view'), 403);

        $user = $request->user();
        $manage = $user->can('schedules.manage');
        $from = now($user->organization->timezone)->startOfDay();

        $assignments = ShiftAssignment::query()
            ->with(['shift.department:id,name', 'shift.location:id,name', 'staffMember:id,uuid,first_name,last_name'])
            ->whereDate('work_date', '>=', $from->toDateString())
            ->when(! $manage, function ($query) use ($user): void {
                $query->where('staff_member_id', $user->staffMember?->getKey() ?? 0)
                    ->whereHas('shift', fn ($shift) => $shift->where('status', 'published'));
            })
            ->orderBy('work_date')
            ->paginate(30);

        return view('tenant.schedules.index', [
            'assignments' => $assignments,
            'shifts' => $manage ? Shift::query()->with(['department:id,name', 'location:id,name'])->latest()->limit(30)->get() : collect(),
            'canManage' => $manage,
            'lesson' => $user->organization->isEducational(),
            'departments' => Department::query()->orderBy('name')->get(['uuid', 'name']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
            'staff' => $manage ? StaffMember::query()->orderBy('last_name')->limit(200)->get(['uuid', 'first_name', 'last_name']) : collect(),
        ]);
    }

    public function store(StoreShiftRequest $request, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();

        $schedules->create(
            $request->user(),
            $input['name'],
            $request->startsAt(),
            $request->endsAt(),
            now()->parse($input['effective_from']),
            isset($input['effective_until']) ? now()->parse($input['effective_until']) : null,
            isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null,
            isset($input['location_uuid']) ? Location::query()->where('uuid', $input['location_uuid'])->first() : null,
        );

        return back()->with('status', 'Schedule saved as a draft.');
    }

    public function publish(Request $request, Shift $shift, ScheduleService $schedules): RedirectResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);
        $schedules->publish($request->user(), $shift);

        return back()->with('status', 'Schedule published.');
    }

    public function assign(AssignShiftRequest $request, Shift $shift, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();

        try {
            $schedules->assign(
                $request->user(),
                $shift,
                StaffMember::query()->where('uuid', $input['staff_uuid'])->firstOrFail(),
                now()->parse($input['work_date']),
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return back()->with('status', 'Assignment saved.');
    }
}
