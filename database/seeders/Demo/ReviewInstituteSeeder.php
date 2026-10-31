<?php

namespace Database\Seeders\Demo;

use App\Enums\OrganizationType;
use Illuminate\Database\Seeder;

/**
 * Fictional Review Institute — an educational tenant used in tests and demos.
 */
class ReviewInstituteSeeder extends Seeder
{
    public const SLUG = 'fictional-review-institute';

    public function run(): void
    {
        $organization = DemoAccount::organization(self::SLUG, [
            'name' => 'Fictional Review Institute',
            'type' => OrganizationType::EducationalInstitution,
            'contact_email' => 'registry@review.orbit-demo.test',
            'contact_phone' => '+233302000401',
            'city' => 'Accra',
            'settings' => [
                'demo' => true,
                'demo_label' => 'Fictional educational tenant for reviews and QA. Not a real school.',
            ],
        ]);

        DemoAccount::preferSubdomain($organization, 'review');
    }
}
