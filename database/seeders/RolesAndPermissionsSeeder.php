<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy. Source of truth is config/orbit.php.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = config('auth.defaults.guard', 'web');

        $roleNames = array_merge(
            array_keys(config('orbit.roles.platform', [])),
            array_keys(config('orbit.roles.tenant', [])),
        );

        $roles = [];

        foreach ($roleNames as $name) {
            $roles[$name] = Role::findOrCreate($name, $guard);
        }

        foreach (config('orbit.permissions', []) as $permissionName => $grantedTo) {
            $permission = Permission::findOrCreate($permissionName, $guard);

            foreach ($grantedTo as $roleName) {
                if (isset($roles[$roleName]) && ! $roles[$roleName]->hasPermissionTo($permission)) {
                    $roles[$roleName]->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
