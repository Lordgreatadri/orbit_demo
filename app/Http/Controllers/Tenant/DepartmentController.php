<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\DepartmentRequest;
use App\Models\Department;
use App\Models\StaffMember;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->withCount(['staffMembers' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('tenant.departments.index', [
            'departments' => $departments,
            'canManage' => $request->user()->can('create', Department::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Department::class);

        return view('tenant.departments.form', [
            'department' => new Department(['is_active' => true]),
            'people' => $this->headChoices($request),
            'headFind' => trim((string) $request->query('head_find', '')),
        ]);
    }

    public function edit(Request $request, Department $department): View
    {
        $this->authorize('update', $department);
        $department->load('head');
        $department->loadCount('staffMembers');

        return view('tenant.departments.form', [
            'department' => $department,
            'people' => $this->headChoices($request, $department),
            'headFind' => trim((string) $request->query('head_find', '')),
        ]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->departmentAttributes());

        Audit::log('department.created', $department, ['name' => $department->name], $request->user());

        return redirect()->route('org.departments.index')->with('status', __('Department ":name" created.', ['name' => $department->name]));
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->fill($request->departmentAttributes());
        $changes = array_keys($department->getDirty());
        $department->save();

        if ($changes !== []) {
            Audit::log('department.updated', $department, ['fields' => $changes], $request->user());
        }

        return redirect()->route('org.departments.index')->with('status', __('Department ":name" saved.', ['name' => $department->name]));
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        if ($department->staffMembers()->exists()) {
            return redirect()->back(fallback: route('org.departments.index'))->withErrors([
                'department' => __('":name" still has staff assigned. Move them to another department or mark the department inactive instead.', ['name' => $department->name]),
            ]);
        }

        $department->delete();

        Audit::log('department.deleted', null, ['name' => $department->name, 'uuid' => $department->uuid], $request->user());

        return redirect()->route('org.departments.index')->with('status', __('Department ":name" deleted.', ['name' => $department->name]));
    }

    /**
     * @return Collection<int, StaffMember>
     */
    private function headChoices(Request $request, ?Department $department = null): Collection
    {
        $term = trim((string) $request->query('head_find', ''));
        $people = strlen($term) < 2
            ? collect()
            : StaffMember::query()->search($term)->orderBy('last_name')->limit(30)->get(['id', 'uuid', 'first_name', 'last_name']);

        $head = $department?->head;
        $old = $request->old('head');

        if (is_string($old) && $old !== '') {
            $picked = StaffMember::query()->where('uuid', $old)->first();

            if ($picked !== null) {
                $head = $picked;
            }
        }

        if ($head !== null && $people->doesntContain(fn (StaffMember $person): bool => (int) $person->getKey() === (int) $head->getKey())) {
            $people->prepend($head);
        }

        return $people;
    }
}
