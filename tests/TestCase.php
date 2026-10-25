<?php

namespace Tests;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the role/permission reference data once per test database.
     * (The full DatabaseSeeder is covered by tests/Feature/Database/SeedingTest.)
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        // Views must render without a compiled asset manifest.
        $this->withoutVite();
    }

    protected function createOrganizationAdmin(?Organization $organization = null, array $attributes = []): User
    {
        $organization ??= Organization::factory()->create();

        return User::factory()
            ->forOrganization($organization)
            ->withRole('organization_admin')
            ->create($attributes);
    }

    protected function createMember(?Organization $organization = null, string $role = 'member', array $attributes = []): User
    {
        $organization ??= Organization::factory()->create();

        return User::factory()
            ->forOrganization($organization)
            ->withRole($role)
            ->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'organization_name' => 'Fictional Institute of Testing',
            'organization_type' => 'educational_institution',
            'timezone' => 'Africa/Accra',
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'email' => 'ama.mensah@example.test',
            'phone' => '+233201234567',
            'password' => 'CorrectHorse42',
            'password_confirmation' => 'CorrectHorse42',
            'terms' => '1',
        ], $overrides);
    }
}
