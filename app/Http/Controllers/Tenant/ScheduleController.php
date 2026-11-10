<?php

namespace App\Http\Controllers\Tenant;

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
            ->orderBy('work_date')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $people = collect();
        if ($manage && strlen($find) >= 2) {
            $people = StaffMember::query()->search($find)->orderBy('last_name')->limit(30)->get(['uuid', 'first_name', 'last_name']);
        }

        return view('tenant.schedules.index', [
            'assignments' => $assignments,
            'shifts' => $manage
                ? Shift::query()->with(['department:id,name', 'location:id,name', 'arrangement:id,name,kind'])->latest('id')->paginate(15, ['*'], 'shifts')->withQueryString()
                : collect(),
            'patterns' => $manage ? WorkPattern::query()->with(['arrangement:id,name', 'department:id,name'])->latest('id')->paginate(10, ['*'], 'patterns')->withQueryString() : collect(),
            'calendar' => $manage
                ? NonWorkingDay::query()->where('observed_on', '>=', $from->toDateString())->orderBy('observed_on')->paginate(15, ['*'], 'days')->withQueryString()
                : collect(),
            'canManage' => $manage,
            'lesson' => $user->organization->isEducational(),
            'departments' => $this->choices(Department::query(), 'name', $unit),
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

        $schedules->create(
            $request->user(),
            $input['name'],
            $request->startsAt(),
            $request->endsAt(),
            now()->parse($input['effective_from']),
            isset($input['effective_until']) ? now()->parse($input['effective_until']) : null,
            isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null,
            isset($input['location_uuid']) ? Location::query()->where('uuid', $input['location_uuid'])->first() : null,
            isset($input['arrangement_uuid']) ? WorkArrangement::query()->where('uuid', $input['arrangement_uuid'])->first() : null,
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

        $schedules->savePattern(
            $request->user(),
            $input['name'],
            $request->startsAt(),
            $request->endsAt(),
            $input['weekdays'],
            now()->parse($input['effective_from']),
            isset($input['effective_until']) ? now()->parse($input['effective_until']) : null,
            isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null,
            isset($input['location_uuid']) ? Location::query()->where('uuid', $input['location_uuid'])->first() : null,
            isset($input['arrangement_uuid']) ? WorkArrangement::query()->where('uuid', $input['arrangement_uuid'])->first() : null,
        );

        return back()->with('status', 'Working pattern saved. Apply it to put people on the roster.');
    }

    public function applyPattern(ApplyWorkPatternRequest $request, WorkPattern $pattern, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();

        try {
            $count = $schedules->applyPattern(
                $request->user(),
                $pattern,
                now()->parse($input['from']),
                now()->parse($input['until']),
                isset($input['department_uuid']) ? Department::query()->where('uuid', $input['department_uuid'])->first() : null,
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()]);
        }

        return back()->with('status', $count.' assignment'.($count === 1 ? '' : 's').' added from '.$pattern->name.'.');
    }

    public function nonWorking(StoreNonWorkingDayRequest $request, ScheduleService $schedules): RedirectResponse
    {
        $input = $request->validated();
        $schedules->markNonWorking($request->user(), now()->parse($input['observed_on']), $input['name'], $input['kind']);

        return back()->with('status', 'Non-working day saved. Working-day leave and pattern apply skip that date.');
    }

    /**
     * @param  Builder<Model>  $query
     * @return Collection<int, Model>
     */
    private function choices($query, string $column, string $term)
    {
        return $query
            ->when(strlen($term) >= 2, fn ($builder) => $builder->where($column, 'like', '%'.$term.'%'))
            ->orderBy($column)
            ->limit(60)
            ->get(['uuid', 'name']);
    }
}
