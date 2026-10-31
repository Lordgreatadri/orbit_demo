<?php

namespace Database\Seeders\Demo;

use App\Enums\DomainType;
use App\Enums\OrganizationType;
use App\Tenancy\Hostname;
use Illuminate\Database\Seeder;

/**
 * Fictional Mobile Content Ltd workspace used as the primary commercial demo.
 * Hostnames: mobilecontent.{base} (primary) and mcc.{base} (alias).
 */
class MobileContentOrganizationSeeder extends Seeder
{
    public const SLUG = 'mobile-content';

    public function run(): void
    {
        $organization = DemoAccount::organization(self::SLUG, [
            'name' => 'Mobile Content Ltd',
            'type' => OrganizationType::Business,
            'contact_email' => 'workspace@mcc.orbit-demo.test',
            'contact_phone' => '+233302000301',
            'address_line' => 'Sample office record, Accra',
            'city' => 'Accra',
            'settings' => [
                'demo' => true,
                'demo_label' => 'Fictional sample for Mobile Content Ltd. Not a live customer record.',
            ],
        ]);

        DemoAccount::preferSubdomain($organization, 'mobilecontent');

        $alias = Hostname::platformHostFor('mcc');
        $organization->domains()->updateOrCreate(
            ['domain' => $alias],
            [
                'type' => DomainType::Alias,
                'is_primary' => false,
                'is_active' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
        );

        $organization->branding()->update([
            'display_name' => 'Mobile Content Ltd',
            'primary_color' => '#0F766E',
            'secondary_color' => '#0D3B43',
            'accent_color' => '#3B6EA5',
            'login_heading' => 'Welcome to Mobile Content',
            'login_message' => 'Sign in to the Mobile Content Ltd workspace.',
            'published_at' => now(),
        ]);
    }
}
