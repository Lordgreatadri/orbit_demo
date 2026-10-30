<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Structure\DepartmentRequest;
use App\Models\Department;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function store(DepartmentRequest $request): RedirectResponse
    {
        $department = Department::query()->create($request->validated());

        Audit::log('department.created', $department, ['name' => $department->name], $request->user());

        return redirect()->route('org.departments.index')->with('status', __('Department ":name" created.', ['name' => $department->name]));
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->fill($request->validated());
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
            return redirect()->route('org.departments.index')->withErrors([
                'department' => __('":name" still has staff assigned. Move them to another department or mark the department inactive instead.', ['name' => $department->name]),
            ]);
        }

        $department->delete();

        Audit::log('department.deleted', null, ['name' => $department->name, 'uuid' => $department->uuid], $request->user());

        return redirect()->route('org.departments.index')->with('status', __('Department ":name" deleted.', ['name' => $department->name]));
    }
}
