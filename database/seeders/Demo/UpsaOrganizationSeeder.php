<?php

namespace Database\Seeders\Demo;

use App\Enums\OrganizationType;
use Illuminate\Database\Seeder;

/**
 * Fictional university sample inspired by the public UPSA students page.
 * Faculties and sites are a demo structure, not a live enrolment extract.
 *
 * @see https://upsa.edu.gh/students/
 */
class UpsaOrganizationSeeder extends Seeder
{
    public const SLUG = 'upsa-demo';

    public function run(): void
    {
        $organization = DemoAccount::organization(self::SLUG, [
            'name' => 'University of Professional Studies, Accra (Demo)',
            'type' => OrganizationType::EducationalInstitution,
            'contact_email' => 'demo.registry@upsa.orbit-demo.test',
            'contact_phone' => '+233303000201',
            'address_line' => 'Sample campus record, Legon',
            'city' => 'Accra',
            'settings' => [
                'demo' => true,
                'demo_label' => 'Fictional university sample for local testing. Not a live UPSA record and not connected to university systems.',
                'public_reference' => 'https://upsa.edu.gh/students/',
                'locations' => [
                    ['name' => 'UPSA Main Campus — Legon', 'kind' => 'campus', 'city' => 'Accra'],
                    ['name' => 'Weekend School — Accra', 'kind' => 'campus', 'city' => 'Accra'],
                    ['name' => 'School of Graduate Studies — Legon', 'kind' => 'campus', 'city' => 'Accra'],
                ],
                'departments' => [
                    'Registry',
                    'Faculty of Accounting and Finance',
                    'Faculty of Management Studies',
                    'Faculty of Information Technology and Communication Studies',
                    'Faculty of Law',
                    'School of Graduate Studies',
                    'Student Affairs',
                ],
                'sample_directory' => [
                    'akua.sarpong@upsa.orbit-demo.test' => [
                        'member_id' => 'UPSA-DEMO-2001',
                        'department' => 'Registry',
                        'location' => 'UPSA Main Campus — Legon',
                        'work_type' => 'office',
                    ],
                    'nana.yeboah@upsa.orbit-demo.test' => [
                        'member_id' => 'UPSA-DEMO-2002',
                        'department' => 'Student Affairs',
                        'location' => 'UPSA Main Campus — Legon',
                        'work_type' => 'office',
                    ],
                    'selorm.adjei@upsa.orbit-demo.test' => [
                        'member_id' => 'UPSA-DEMO-2003',
                        'department' => 'Faculty of Information Technology and Communication Studies',
                        'location' => 'UPSA Main Campus — Legon',
                        'work_type' => 'lecturer',
                    ],
                    'maame.owusu@upsa.orbit-demo.test' => [
                        'member_id' => 'UPSA-DEMO-2004',
                        'department' => 'Faculty of Accounting and Finance',
                        'location' => 'UPSA Main Campus — Legon',
                        'work_type' => 'student',
                    ],
                ],
            ],
        ]);

        DemoAccount::preferSubdomain($organization, 'upsa');
    }
}
