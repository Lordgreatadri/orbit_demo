<?php

namespace Tests\Feature\Database;

use App\Models\Country;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeedingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_database_seeder_runs_cleanly_and_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $expectedRoles = array_merge(
            array_keys(config('orbit.roles.platform')),
            array_keys(config('orbit.roles.tenant')),
        );

        $this->assertSame(count($expectedRoles), Role::query()->count());
        $this->assertSame(count(config('orbit.permissions')), Permission::query()->count());
        $this->assertGreaterThan(0, Country::query()->count());
        $this->assertSame(Country::query()->count(), Country::query()->whereNotNull('uuid')->distinct('uuid')->count('uuid'));
    }

    #[Test]
    public function permissions_are_granted_to_the_configured_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (config('orbit.permissions') as $permission => [$description, $roles]) {
            $this->assertNotSame('', $description, "Permission [{$permission}] needs a description.");
            $this->assertSame(Permission::moduleFromName($permission), Permission::findByName($permission)->module);

            foreach ($roles as $roleName) {
                $this->assertTrue(
                    Role::findByName($roleName)->hasPermissionTo($permission),
                    "Role [{$roleName}] should have permission [{$permission}]."
                );
            }
        }

        $this->assertFalse(Role::findByName('member')->hasPermissionTo('users.invite'));
        $this->assertFalse(Role::findByName('organization_admin')->hasPermissionTo('platform.access'));
        $this->assertFalse(Role::findByName('platform_support')->hasPermissionTo('users.view'));

        // Roles carry their scope and are marked as system-managed.
        $this->assertSame('platform', Role::findByName('platform_super_admin')->scope->value);
        $this->assertSame('tenant', Role::findByName('organization_admin')->scope->value);
        $this->assertTrue(Role::findByName('member')->is_system);
        $this->assertTrue(Permission::findByName('platform.access')->is_system);
    }

    #[Test]
    public function platform_permissions_are_never_granted_to_tenant_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (Role::query()->tenant()->get() as $role) {
            $this->assertSame(
                0,
                $role->permissions()->where('module', 'platform')->count(),
                "Tenant role [{$role->name}] must not hold platform permissions."
            );
        }
    }
}
