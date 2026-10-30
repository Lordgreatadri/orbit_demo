<?php

namespace App\Http\Controllers\Platform;

use App\Enums\RoleScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role CRUD for the platform console. System roles (from config) can have
 * their description and permission set adjusted but keep their name and scope
 * and cannot be deleted. Platform-only permissions can never be attached to a
 * tenant-scoped role.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['permissions', 'users'])->orderBy('scope')->orderBy('name')->get();

        return view('platform.roles.index', [
            'roles' => $roles->groupBy(fn (Role $r) => $r->scope->value),
            'canCreate' => $request->user()->can('create', Role::class),
            'canUpdate' => $request->user()->can('create', Role::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Role::class);

        return view('platform.roles.form', $this->formData() + ['role' => new Role(['scope' => RoleScope::Tenant]), 'selected' => []]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $permissions = $this->allowedPermissions(RoleScope::from($request->validated('scope')), $request->validated('permissions'));

        $role = DB::transaction(function () use ($request, $permissions): Role {
            $role = Role::query()->create([
                'name' => $request->validated('name'),
                'guard_name' => config('auth.defaults.guard', 'web'),
                'scope' => $request->validated('scope'),
                'description' => $request->validated('description'),
                'is_system' => false,
            ]);

            $role->syncPermissions($permissions);

            Audit::log('platform.role.created', $role, ['scope' => $role->scope->value, 'permissions' => $permissions], $request->user());

            return $role;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')->with('status', __('Role ":role" created.', ['role' => $role->label()]));
    }

    public function edit(Request $request, Role $role): View
    {
        $this->authorize('update', $role);

        $role->load('permissions:id,name');

        return view('platform.roles.form', $this->formData() + [
            'role' => $role,
            'selected' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $permissions = $this->allowedPermissions($role->scope, $request->validated('permissions'));

        DB::transaction(function () use ($request, $role, $permissions): void {
            $before = $role->permissions()->pluck('name')->sort()->values()->all();

            $role->fill([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
            ])->save();

            $role->syncPermissions($permissions);

            $after = collect($permissions)->sort()->values()->all();

            Audit::log('platform.role.updated', $role, [
                'permissions_before' => $before,
                'permissions_after' => $after,
            ], $request->user());
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')->with('status', __('Role ":role" saved.', ['role' => $role->label()]));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return redirect()->route('admin.roles.index')->withErrors([
                'role' => __('":role" is still assigned to users. Reassign them before deleting the role.', ['role' => $role->label()]),
            ]);
        }

        $name = $role->label();
        $role->delete();

        Audit::log('platform.role.deleted', null, ['name' => $role->name, 'scope' => $role->scope->value], $request->user());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('admin.roles.index')->with('status', __('Role ":role" deleted.', ['role' => $name]));
    }

    /**
     * Drop permissions that are not valid for the scope (platform.* for tenant
     * roles, and vice versa), so a crafted form cannot cross the boundary.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    private function allowedPermissions(RoleScope $scope, array $requested): array
    {
        $query = $scope === RoleScope::Platform
            ? Permission::query()->platformOnly()
            : Permission::query()->tenantGrantable();

        return $query->whereIn('name', $requested)->orderBy('name')->pluck('name')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'permissions' => Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module'),
            'moduleLabels' => config('orbit.permission_modules', []),
            'scopes' => RoleScope::options(),
        ];
    }
}
