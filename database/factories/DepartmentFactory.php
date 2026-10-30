<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->randomElement(['Administration', 'Finance', 'Human Resources', 'Operations', 'Legal', 'IT', 'Field Services', 'Research']).' '.fake()->unique()->numberBetween(1, 9999),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'is_active' => true,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->getKey()]);
    }
}
