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
                'sample_directory' => self::directory(),
            ],
        ]);

        DemoAccount::preferSubdomain($organization, 'upsa');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function directory(): array
    {
        return DirectoryExpansion::pad([
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
                'category' => 'SUPPORT',
                'grade' => 'Student',
                'employment_type' => 'student',
                'started_on' => '2024-08-01',
            ],
            'kwame.asare@upsa.orbit-demo.test' => [
                'member_id' => 'UPSA-DEMO-2005',
                'department' => 'Faculty of Information Technology and Communication Studies',
                'location' => 'UPSA Main Campus — Legon',
                'work_type' => 'lecturer',
                'category' => 'TEACH',
                'grade' => 'Senior',
                'employment_type' => 'permanent',
                'started_on' => '2012-09-01',
            ],
            'ama.boateng@upsa.orbit-demo.test' => [
                'member_id' => 'UPSA-DEMO-2006',
                'department' => 'Registry',
                'location' => 'UPSA Main Campus — Legon',
                'work_type' => 'office',
                'category' => 'ADMIN',
                'grade' => 'Officer',
                'employment_type' => 'permanent',
                'started_on' => '2018-01-08',
            ],
            'kofi.mensah@upsa.orbit-demo.test' => [
                'member_id' => 'UPSA-DEMO-2007',
                'department' => 'Student Affairs',
                'location' => 'Weekend School — Accra',
                'work_type' => 'hybrid',
                'category' => 'SUPPORT',
                'grade' => 'Officer',
                'employment_type' => 'contract',
                'started_on' => '2023-04-01',
            ],
            'adjoa.tetteh@upsa.orbit-demo.test' => [
                'member_id' => 'UPSA-DEMO-2008',
                'department' => 'School of Graduate Studies',
                'location' => 'School of Graduate Studies — Legon',
                'work_type' => 'remote',
                'category' => 'TEACH',
                'grade' => 'Lecturer',
                'employment_type' => 'permanent',
                'started_on' => '2016-08-15',
            ],
        ], 'upsa.orbit-demo.test', 'UPSA-DEMO', [
            'Registry',
            'Faculty of Accounting and Finance',
            'Faculty of Management Studies',
            'Faculty of Information Technology and Communication Studies',
            'Faculty of Law',
            'School of Graduate Studies',
            'Student Affairs',
        ], [
            'UPSA Main Campus — Legon',
            'Weekend School — Accra',
            'School of Graduate Studies — Legon',
        ], [
            ['work_type' => 'office', 'category' => 'ADMIN', 'grade' => 'Officer', 'employment_type' => 'permanent', 'started_on' => '2017-01-09', 'status' => 'active'],
            ['work_type' => 'lecturer', 'category' => 'TEACH', 'grade' => 'Lecturer', 'employment_type' => 'permanent', 'started_on' => '2013-09-01', 'status' => 'active'],
            ['work_type' => 'student', 'category' => 'SUPPORT', 'grade' => 'Student', 'employment_type' => 'student', 'started_on' => '2024-08-01', 'status' => 'active'],
            ['work_type' => 'hybrid', 'category' => 'SUPPORT', 'grade' => 'Officer', 'employment_type' => 'contract', 'started_on' => '2022-03-01', 'status' => 'active'],
            ['work_type' => 'remote', 'category' => 'TEACH', 'grade' => 'Lecturer', 'employment_type' => 'permanent', 'started_on' => '2015-08-01', 'status' => 'on_leave'],
            ['work_type' => 'office', 'category' => 'ADMIN', 'grade' => 'Officer', 'employment_type' => 'contract', 'started_on' => '2021-11-01', 'status' => 'inactive'],
        ]);
    }
}
