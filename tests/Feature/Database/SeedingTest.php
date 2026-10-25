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

        foreach (config('orbit.permissions') as $permission => $roles) {
            foreach ($roles as $roleName) {
                $this->assertTrue(
                    Role::findByName($roleName)->hasPermissionTo($permission),
                    "Role [{$roleName}] should have permission [{$permission}]."
                );
            }
        }

        $this->assertFalse(Role::findByName('member')->hasPermissionTo('members.invite'));
        $this->assertFalse(Role::findByName('organization_admin')->hasPermissionTo('platform.access'));
    }
}
