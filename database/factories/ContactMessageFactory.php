<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'organization_name' => fake()->company(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'ip_address' => fake()->ipv4(),
            'status' => 'new',
        ];
    }
}
