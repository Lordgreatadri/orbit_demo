<?php

namespace App\Http\Controllers\Tenant;

use App\Analytics\AnalyticsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\ApplyWorkPatternRequest;
use App\Http\Requests\Workforce\AssignShiftRequest;
use App\Http\Requests\Workforce\StoreNonWorkingDayRequest;
use App\Http\Requests\Workforce\StoreShiftRequest;
use App\Http\Requests\Workforce\StoreWorkPatternRequest;
use App\Models\Department;
use App\Models\Location;
use App\Models\NonWorkingDay;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\WorkArrangement;
use App\Models\WorkPattern;
use App\Workforce\AttendanceException;
use App\Workforce\LeavePlanner;
use App\Workforce\ScheduleService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScheduleController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('schedules.view'), 403);

        $user = $request->user();
        $manage = $user->can('schedules.manage');
        $departmentIds = AnalyticsScope::restrictingDepartmentIds($user);
        $timezone = $user->organization->timezone ?: 'UTC';
        $from = now($timezone)->startOfDay();
        $find = trim((string) $request->query('find', ''));
        $unit = trim((string) $request->query('unit', ''));
        $place = trim((string) $request->query('place', ''));

        $assignments = ShiftAssignment::query()
            ->with([
                'shift:id,name,starts_at,ends_at,status,crosses_midnight',
                'arrangement:id,name,kind',
                'location:id,name',
                'staffMember:id,uuid,first_name,last_name',
            ])
            ->where('work_date', '>=', $from->toDateString())
            ->when(! $manage, function ($query) use ($user): void {
                $query->where('staff_member_id', $user->staffMember?->getKey() ?? 0)
                    ->whereHas('shift', fn ($shift) => $shift->where('status', 'published'));
            })
            ->when($manage && $departmentIds !== null, function ($query) use ($departmentIds): void {
                $query->whereIn('staff_member_id', StaffMember::query()
                    ->whereIn('department_id', $departmentIds === [] ? [-1] : $departmentIds)
                    ->select('id'));
            })
            ->orderBy('work_date')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $people = collect();
        if ($manage && strlen($find) >= 2) {
            $people = StaffMember::query()
                ->search($find)
                ->when($departmentIds !== null, fn ($query) => $query->whereIn('department_id', $departmentIds === [] ? [-1] : $departmentIds))
                ->orderBy('last_name')
                ->limit(30)
                ->get(['uuid', 'first_name', 'last_name']);
        }

        return view('tenant.schedules.index', [
            'assignments' => $assignments,
            'shifts' => $manage
                ? Shift::query()
                    ->with(['department:id,name', 'location:id,name', 'arrangement:id,name,kind'])
                    ->when($departmentIds !== null, fn ($query) => $query->whereIn('department_id', $departmentIds === [] ? [-1] : $departmentIds))
                    ->latest('id')
                    ->paginate(15, ['*'], 'shifts')
                    ->withQueryString()
                : collect(),
            'patterns' => $manage
                ? WorkPattern::query()
                    ->with(['arrangement:id,name', 'department:id,name'])
                    ->when($departmentIds !== null, fn ($query) => $query->whereIn('department_id', $departmentIds === [] ? [-1] : $departmentIds))
                    ->latest('id')
                    ->paginate(10, ['*'], 'patterns')
                    ->withQueryString()
                : collect(),
            'calendar' => $manage
                ? NonWorkingDay::query()->where('observed_on', '>=', $from->toDateString())->orderBy('observed_on')->paginate(15, ['*'], 'days')->withQueryString()
                : collect(),
            'canManage' => $manage,
            'lesson' => $user->organization->isEducational(),
            'departments' => $this->choices(
                Department::query()->when($departmentIds !== null, fn ($query) => $query->whereIn('id', $departmentIds === [] ? [-1] : $departmentIds)),
                'name',
                $unit,
            ),
            'locations' => $this->choices(Location::query()->where('is_active', true), 'name', $place),
            'arrangements' => WorkArrangement::query()->where('is_active', true)->orderBy('name')->get(['uuid', 'name', 'kind']),
            'people' => $people,
            'find' => $find,
            'unit' => $unit,
            'place' => $place,
            'needsArrangements' => $manage && WorkArrangement::query()->doesntExist(),
        ]);
    }

    public function provisionArrangements(Request $request, LeavePlanner $planner): RedirectResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);
        $planner->ensureArrangements($request->user()->organization);

        return back()->with('status', 'Standard work arrangements are ready. You can rename them for this organisation.');
    }

    public function store(StoreShiftRequest $request, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();
        $department = isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null;
        abort_unless(AnalyticsScope::departmentAllowed($request->user(), $department?->getKey()), 403);

        $schedules->create(
            $request->user(),
            $input['name'],
            $request->startsAt(),
            $request->endsAt(),
            now()->parse($input['effective_from']),
            isset($input['effective_until']) ? now()->parse($input['effective_until']) : null,
            $department,
            isset($input['location_uuid']) ? Location::query()->where('uuid', $input['location_uuid'])->first() : null,
            isset($input['arrangement_uuid']) ? WorkArrangement::query()->where('uuid', $input['arrangement_uuid'])->first() : null,
        );

        return back()->with('status', 'Schedule saved as a draft.');
    }

    public function publish(Request $request, Shift $shift, ScheduleService $schedules): RedirectResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);
        $this->abortUnlessDepartment($request, $shift->department_id);
        $schedules->publish($request->user(), $shift);

        return back()->with('status', 'Schedule published.');
    }

    public function assign(AssignShiftRequest $request, Shift $shift, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();
        $person = StaffMember::query()->where('uuid', $input['staff_uuid'])->firstOrFail();
        $this->abortUnlessDepartment($request, $shift->department_id);
        $this->abortUnlessDepartment($request, $person->department_id);

        try {
            $schedules->assign(
                $request->user(),
                $shift,
                $person,
                now()->parse($input['work_date']),
                isset($input['arrangement_uuid']) ? WorkArrangement::query()->where('uuid', $input['arrangement_uuid'])->first() : null,
                isset($input['location_uuid']) ? Location::query()->where('uuid', $input['location_uuid'])->first() : null,
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return back()->with('status', 'Assignment saved.');
    }

    public function destroyAssignment(Request $request, ShiftAssignment $assignment, ScheduleService $schedules): RedirectResponse
    {
        abort_unless($request->user()->can('schedules.manage'), 403);
        $assignment->loadMissing(['shift', 'staffMember']);
        $this->abortUnlessDepartment($request, $assignment->shift?->department_id);
        $this->abortUnlessDepartment($request, $assignment->staffMember?->department_id);

        try {
            $schedules->remove($request->user(), $assignment);
        } catch (AttendanceException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return back()->with('status', 'Assignment removed.');
    }

    public function pattern(StoreWorkPatternRequest $request, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();
        $department = isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null;
        abort_unless(AnalyticsScope::departmentAllowed($request->user(), $department?->getKey()), 403);

        $schedules->savePattern(
            $request->user(),
            $input['name'],
            $request->startsAt(),
            $request->endsAt(),
            $input['weekdays'],
            now()->parse($input['effective_from']),
            isset($input['effective_until']) ? now()->parse($input['effective_until']) : null,
            $department,
            isset($input['location_uuid']) ? Location::query()->where('uuid', $input['location_uuid'])->first() : null,
            isset($input['arrangement_uuid']) ? WorkArrangement::query()->where('uuid', $input['arrangement_uuid'])->first() : null,
        );

        return back()->with('status', 'Working pattern saved. Apply it to put people on the roster.');
    }

    public function applyPattern(ApplyWorkPatternRequest $request, WorkPattern $pattern, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();
        $department = isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null;
        $this->abortUnlessDepartment($request, $pattern->department_id);
        $this->abortUnlessDepartment($request, $department?->getKey() ?? $pattern->department_id);

        try {
            $count = $schedules->applyPattern(
                $request->user(),
                $pattern,
                now()->parse($input['from']),
                now()->parse($input['until']),
                $department,
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return back()->with('status', $count.' assignment'.($count === 1 ? '' : 's').' added from '.$pattern->name.'.');
    }

    public function nonWorking(StoreNonWorkingDayRequest $request, ScheduleService $schedules): RedirectResponse
    {
        abort_if(AnalyticsScope::restrictingDepartmentIds($request->user()) !== null, 403);
        $input = $request->validated();
        $schedules->markNonWorking($request->user(), now()->parse($input['observed_on']), $input['name'], $input['kind']);

        return back()->with('status', 'Non-working day saved. Working-day leave and pattern apply skip that date.');
    }

    /**
     * @param  Builder<Model>  $query
     * @return Collection<int, Model>
     */
    private function abortUnlessDepartment(Request $request, mixed $departmentId): void
    {
        abort_unless(
            AnalyticsScope::departmentAllowed($request->user(), $departmentId === null ? null : (int) $departmentId),
            403,
        );
    }

    private function choices($query, string $column, string $term)
    {
        return $query
            ->when(strlen($term) >= 2, fn ($builder) => $builder->where($column, 'like', '%'.$term.'%'))
            ->orderBy($column)
            ->limit(60)
            ->get(['uuid', 'name']);
    }
}
