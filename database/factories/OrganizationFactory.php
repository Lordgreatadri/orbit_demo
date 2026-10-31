<?php

namespace Database\Factories;

use App\Actions\Tenancy\ProvisionOrganizationWorkspace;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organization $organization): void {
            app(ProvisionOrganizationWorkspace::class)->handle($organization);
        });
    }

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'type' => fake()->randomElement(OrganizationType::cases()),
            'status' => OrganizationStatus::Active,
            'timezone' => config('orbit.default_timezone'),
            'country_code' => config('orbit.default_country'),
            'contact_email' => fake()->unique()->safeEmail(),
            'contact_phone' => '+2332'.fake()->numerify('########'),
            'city' => fake()->city(),
            'verified_at' => now(),
        ];
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizationStatus::PendingVerification,
            'verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizationStatus::Suspended,
        ]);
    }
}
