<?php

namespace Database\Factories;

use App\Enums\DomainType;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Tenancy\Hostname;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationDomain>
 */
class OrganizationDomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::lower(Str::random(8));

        return [
            'organization_id' => Organization::factory(),
            'domain' => Hostname::platformHostFor($label),
            'type' => DomainType::PlatformSubdomain,
            'is_primary' => true,
            'is_active' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ];
    }

    public function alias(string $label): static
    {
        return $this->state(fn () => [
            'domain' => Hostname::platformHostFor($label),
            'type' => DomainType::Alias,
            'is_primary' => false,
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function custom(string $host, bool $verified = false): static
    {
        return $this->state(fn () => [
            'domain' => Hostname::normalize($host),
            'type' => DomainType::CustomDomain,
            'is_primary' => false,
            'is_verified' => $verified,
            'is_active' => $verified,
            'verified_at' => $verified ? now() : null,
            'verification_token' => Str::lower(Str::random(40)),
        ]);
    }
}
