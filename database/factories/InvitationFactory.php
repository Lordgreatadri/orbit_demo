<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [, $hash] = Invitation::generateToken();

        return [
            'organization_id' => Organization::factory(),
            'invited_by_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'role' => 'member',
            'token_hash' => $hash,
            'expires_at' => now()->addDays(config('orbit.invitation_ttl_days')),
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function invitedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $user->organization_id,
            'invited_by_id' => $user->getKey(),
        ]);
    }

    /**
     * Use a known raw token so tests can exercise the acceptance URL.
     */
    public function withRawToken(string $raw): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => Invitation::hashToken($raw),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now()->subMinute(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now()->subMinute(),
        ]);
    }
}
