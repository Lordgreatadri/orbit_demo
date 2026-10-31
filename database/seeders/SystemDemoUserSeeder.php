<?php

namespace Database\Seeders;

use App\Models\Organization;
use Database\Seeders\Demo\DemoAccount;
use Database\Seeders\Demo\DemoGuard;
use Database\Seeders\Demo\EpaOrganizationSeeder;
use Database\Seeders\Demo\MobileContentOrganizationSeeder;
use Database\Seeders\Demo\ReviewInstituteSeeder;
use Database\Seeders\Demo\UpsaOrganizationSeeder;
use Illuminate\Database\Seeder;

/**
 * Local-only fictional accounts covering every platform and tenant role.
 *
 * Not called from DatabaseSeeder. Run explicitly:
 * php artisan db:seed --class=SystemDemoUserSeeder
 *
 * The shared password is read from ORBIT_DEMO_PASSWORD and is never stored here.
 */
class SystemDemoUserSeeder extends Seeder
{
    public function run(): void
    {
        DemoGuard::assertAllowed();
        DemoGuard::password();

        $this->call([
            RolesAndPermissionsSeeder::class,
            MobileContentOrganizationSeeder::class,
            EpaOrganizationSeeder::class,
            UpsaOrganizationSeeder::class,
            ReviewInstituteSeeder::class,
            OrganizationWorkspaceBackfillSeeder::class,
        ]);

        $epa = Organization::query()->where('slug', EpaOrganizationSeeder::SLUG)->firstOrFail();
        $upsa = Organization::query()->where('slug', UpsaOrganizationSeeder::SLUG)->firstOrFail();
        $mcc = Organization::query()->where('slug', MobileContentOrganizationSeeder::SLUG)->firstOrFail();
        $review = Organization::query()->where('slug', ReviewInstituteSeeder::SLUG)->firstOrFail();

        foreach ($this->accounts($epa, $upsa, $mcc, $review) as $account) {
            DemoAccount::user($account['profile'], $account['role'], $account['organization']);
        }

        $this->command?->info('Demo accounts are ready. Sign in with ORBIT_DEMO_PASSWORD. These records are fictional and must not be used in production.');
    }

    /**
     * @return array<int, array{profile: array{first_name: string, last_name: string, email: string, phone: string}, role: string, organization: ?Organization}>
     */
    private function accounts(Organization $epa, Organization $upsa, Organization $mcc, Organization $review): array
    {
        return [
            [
                'profile' => ['first_name' => 'Adwoa', 'last_name' => 'Boateng', 'email' => 'platform.admin@orbit-demo.test', 'phone' => '+233302000001'],
                'role' => 'platform_super_admin',
                'organization' => null,
            ],
            [
                'profile' => ['first_name' => 'Kwame', 'last_name' => 'Asare', 'email' => 'platform.support@orbit-demo.test', 'phone' => '+233302000002'],
                'role' => 'platform_support',
                'organization' => null,
            ],
            [
                'profile' => ['first_name' => 'Ama', 'last_name' => 'Mensah', 'email' => 'ama.mensah@epa.orbit-demo.test', 'phone' => '+233302000111'],
                'role' => 'organization_admin',
                'organization' => $epa,
            ],
            [
                'profile' => ['first_name' => 'Esi', 'last_name' => 'Quaye', 'email' => 'esi.quaye@epa.orbit-demo.test', 'phone' => '+233302000112'],
                'role' => 'organization_manager',
                'organization' => $epa,
            ],
            [
                'profile' => ['first_name' => 'Kojo', 'last_name' => 'Appiah', 'email' => 'kojo.appiah@epa.orbit-demo.test', 'phone' => '+233302000113'],
                'role' => 'member',
                'organization' => $epa,
            ],
            [
                'profile' => ['first_name' => 'Abena', 'last_name' => 'Darko', 'email' => 'abena.darko@epa.orbit-demo.test', 'phone' => '+233302000114'],
                'role' => 'member',
                'organization' => $epa,
            ],
            [
                'profile' => ['first_name' => 'Akua', 'last_name' => 'Sarpong', 'email' => 'akua.sarpong@upsa.orbit-demo.test', 'phone' => '+233303000211'],
                'role' => 'organization_admin',
                'organization' => $upsa,
            ],
            [
                'profile' => ['first_name' => 'Nana', 'last_name' => 'Yeboah', 'email' => 'nana.yeboah@upsa.orbit-demo.test', 'phone' => '+233303000212'],
                'role' => 'organization_manager',
                'organization' => $upsa,
            ],
            [
                'profile' => ['first_name' => 'Selorm', 'last_name' => 'Adjei', 'email' => 'selorm.adjei@upsa.orbit-demo.test', 'phone' => '+233303000213'],
                'role' => 'member',
                'organization' => $upsa,
            ],
            [
                'profile' => ['first_name' => 'Maame', 'last_name' => 'Owusu', 'email' => 'maame.owusu@upsa.orbit-demo.test', 'phone' => '+233303000214'],
                'role' => 'student',
                'organization' => $upsa,
            ],
            [
                'profile' => ['first_name' => 'Rexford', 'last_name' => 'Mensah', 'email' => 'rexford.mensah@mcc.orbit-demo.test', 'phone' => '+233302000302'],
                'role' => 'organization_admin',
                'organization' => $mcc,
            ],
            [
                'profile' => ['first_name' => 'Ama', 'last_name' => 'Review', 'email' => 'ama.review@review.orbit-demo.test', 'phone' => '+233302000402'],
                'role' => 'organization_admin',
                'organization' => $review,
            ],
        ];
    }
}
