<?php

namespace Database\Seeders;

use App\Enums\RoleScope;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy. Source of truth is config/orbit.php.
 *
 * Only rows flagged `is_system` are managed here. Roles and permissions created
 * by a platform super administrator (is_system = false) are left untouched, and
 * system permissions that disappear from config are removed.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        /** @var array<string, Role> $roles */
        $roles = [];

        foreach (['platform' => RoleScope::Platform, 'tenant' => RoleScope::Tenant] as $key => $scope) {
            foreach (config("orbit.roles.{$key}", []) as $name => $label) {
                $role = Role::query()->firstOrNew(['name' => $name, 'guard_name' => $guard]);
                $role->forceFill([
                    'scope' => $scope,
                    'description' => config('orbit.role_descriptions.'.$name),
                    'is_system' => true,
                ])->save();

                $roles[$name] = $role;
            }
        }

        $configured = [];

        foreach (config('orbit.permissions', []) as $permissionName => [$description, $grantedTo]) {
            $configured[] = $permissionName;

            $permission = Permission::query()->firstOrNew(['name' => $permissionName, 'guard_name' => $guard]);
            $permission->forceFill([
                'module' => Permission::moduleFromName($permissionName),
                'description' => $description,
                'is_system' => true,
            ])->save();

            foreach ($grantedTo as $roleName) {
                if (isset($roles[$roleName]) && ! $roles[$roleName]->hasPermissionTo($permission)) {
                    $roles[$roleName]->givePermissionTo($permission);
                }
            }
        }

        // Retired system permissions (renamed or removed from config).
        Permission::query()
            ->where('guard_name', $guard)
            ->where('is_system', true)
            ->whereNotIn('name', $configured)
            ->get()
            ->each(fn (Permission $stale) => $stale->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
