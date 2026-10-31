<?php

namespace Database\Factories;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use App\Models\OrganizationBranding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationBranding>
 */
class OrganizationBrandingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'display_name' => fake()->company(),
            'primary_color' => '#0F766E',
            'secondary_color' => '#0D3B43',
            'accent_color' => '#3B6EA5',
            'theme_mode' => BrandThemeMode::User,
            'font_family' => BrandFont::InstrumentSans,
            'login_heading' => 'Sign in',
            'login_message' => 'Welcome back.',
            'published_at' => now(),
        ];
    }
}
