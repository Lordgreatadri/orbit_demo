<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->city().' Office',
            'type' => LocationType::Office,
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->getKey()]);
    }

    public function withGeofence(): static
    {
        return $this->state(fn () => [
            'latitude' => fake()->latitude(4.5, 11),
            'longitude' => fake()->longitude(-3.3, 1.2),
            'geofence_radius_m' => 150,
        ]);
    }
}
