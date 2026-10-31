<?php

namespace Database\Seeders\Demo;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Fictional Ghana EPA demonstration tenant.
 *
 * Locations and departments follow docs/requirements/05-epa-demo.md.
 * There is no locations table yet, so the sample structure lives in
 * organizations.settings and is labelled as demo data.
 */
class EpaOrganizationSeeder extends Seeder
{
    public const SLUG = 'epa-ghana-demo';

    public function run(): void
    {
        $organization = DemoAccount::organization(self::SLUG, [
            'name' => 'Environmental Protection Agency — Ghana',
            'type' => OrganizationType::PublicInstitution,
            'contact_email' => 'demo.registry@epa.orbit-demo.test',
            'contact_phone' => '+233302000101',
            'address_line' => 'Sample head office record, Accra',
            'city' => 'Accra',
            'settings' => [
                'demo' => true,
                'demo_label' => 'Fictional sample for the Ghana EPA demonstration. Not a live agency record and not connected to EPA systems.',
                'locations' => [
                    ['name' => 'EPA Head Office — Accra', 'kind' => 'office', 'city' => 'Accra'],
                    ['name' => 'EPA Tema Office', 'kind' => 'office', 'city' => 'Tema'],
                    ['name' => 'EPA Kumasi Office', 'kind' => 'office', 'city' => 'Kumasi'],
                    ['name' => 'EPA Takoradi Office', 'kind' => 'office', 'city' => 'Takoradi'],
                    ['name' => 'Field Operations', 'kind' => 'field', 'city' => null],
                ],
                'departments' => [
                    'Administration',
                    'Finance',
                    'Human Resources',
                    'Operations',
                    'Environmental Monitoring',
                    'Field Services',
                    'IT',
                    'Legal',
                ],
                'sample_directory' => [
                    'ama.mensah@epa.orbit-demo.test' => [
                        'member_id' => 'EPA-DEMO-1001',
                        'department' => 'Administration',
                        'location' => 'EPA Head Office — Accra',
                        'work_type' => 'office',
                    ],
                    'esi.quaye@epa.orbit-demo.test' => [
                        'member_id' => 'EPA-DEMO-1002',
                        'department' => 'Operations',
                        'location' => 'EPA Head Office — Accra',
                        'work_type' => 'office',
                    ],
                    'kojo.appiah@epa.orbit-demo.test' => [
                        'member_id' => 'EPA-DEMO-1003',
                        'department' => 'Field Services',
                        'location' => 'Field Operations',
                        'work_type' => 'field',
                    ],
                    'abena.darko@epa.orbit-demo.test' => [
                        'member_id' => 'EPA-DEMO-1004',
                        'department' => 'Finance',
                        'location' => 'EPA Kumasi Office',
                        'work_type' => 'office',
                    ],
                ],
                'sample_metrics' => [
                    'label' => 'Illustrative only. Not live EPA figures.',
                    'members' => 24,
                    'present' => 18,
                    'late' => 3,
                    'absent' => 2,
                    'on_leave' => 1,
                    'office' => 16,
                    'field' => 6,
                    'remote' => 2,
                ],
            ],
        ]);

        DemoAccount::preferSubdomain($organization, 'epa');
    }

    public static function organization(): Organization
    {
        return Organization::query()->where('slug', self::SLUG)->firstOrFail();
    }
}
