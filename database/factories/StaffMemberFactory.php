<?php

namespace Database\Factories;

use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'staff_number' => 'STF-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2332'.fake()->numerify('########'),
            'job_title' => fake()->jobTitle(),
            'work_type' => WorkType::Office,
            'status' => StaffStatus::Active,
            'started_on' => fake()->dateTimeBetween('-5 years', '-1 month'),
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->getKey()]);
    }

    public function linkedTo(User $user): static
    {
        return $this->state(fn () => [
            'organization_id' => $user->organization_id,
            'user_id' => $user->getKey(),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ]);
    }
}
