<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\PermissionRequest;
use App\Models\Permission;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission catalogue. System permissions come from config and are read-only
 * except for their description; custom permissions can be added for roles to
 * use (checks in code must still reference them for them to have effect).
 */
class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Permission::class);

        $permissions = Permission::query()
            ->withCount('roles')
            ->orderBy('module')
            ->orderBy('name')
            ->get();

        return view('platform.permissions.index', [
            'permissions' => $permissions->groupBy('module'),
            'moduleLabels' => config('orbit.permission_modules', []),
            'canManage' => $request->user()->can('create', Permission::class),
        ]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::query()->create([
            'name' => $request->validated('name'),
            'guard_name' => config('auth.defaults.guard', 'web'),
            'module' => Permission::moduleFromName($request->validated('name')),
            'description' => $request->validated('description'),
            'is_system' => false,
        ]);

        Audit::log('platform.permission.created', $permission, ['name' => $permission->name], $request->user());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.permissions.index')->with('status', __('Permission ":name" created.', ['name' => $permission->name]));
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->fill([
            'name' => $request->validated('name'),
            'module' => Permission::moduleFromName($request->validated('name')),
            'description' => $request->validated('description'),
        ]);

        $changes = array_keys($permission->getDirty());
        $permission->save();

        if ($changes !== []) {
            Audit::log('platform.permission.updated', $permission, ['fields' => $changes], $request->user());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return redirect()->route('admin.permissions.index')->with('status', __('Permission ":name" saved.', ['name' => $permission->name]));
    }

    public function destroy(Request $request, Permission $permission): RedirectResponse
    {
        $this->authorize('delete', $permission);

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            return redirect()->route('admin.permissions.index')->withErrors([
                'permission' => __('":name" is still granted to roles or users. Remove those grants first.', ['name' => $permission->name]),
            ]);
        }

        $name = $permission->name;
        $permission->delete();

        Audit::log('platform.permission.deleted', null, ['name' => $name], $request->user());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.permissions.index')->with('status', __('Permission ":name" deleted.', ['name' => $name]));
    }
}
