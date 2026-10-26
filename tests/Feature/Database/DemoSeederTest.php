<?php

namespace Tests\Feature\Database;

use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\Demo\DemoAccount;
use Database\Seeders\Demo\EpaOrganizationSeeder;
use Database\Seeders\Demo\UpsaOrganizationSeeder;
use Database\Seeders\SystemDemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'OrbitDemo2026';

    #[Test]
    public function the_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('production');

        (new SystemDemoUserSeeder)->run();
    }

    #[Test]
    public function the_demo_seeder_requires_a_local_password(): void
    {
        config(['orbit.demo_password' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ORBIT_DEMO_PASSWORD');

        (new SystemDemoUserSeeder)->run();
    }

    #[Test]
    public function it_seeds_both_demo_organisations_and_every_role(): void
    {
        config(['orbit.demo_password' => self::PASSWORD]);

        $this->seed(SystemDemoUserSeeder::class);
        $this->seed(SystemDemoUserSeeder::class);

        $epa = Organization::query()->where('slug', EpaOrganizationSeeder::SLUG)->firstOrFail();
        $upsa = Organization::query()->where('slug', UpsaOrganizationSeeder::SLUG)->firstOrFail();

        $this->assertSame(2, Organization::query()->count());
        $this->assertSame('Environmental Protection Agency — Ghana', $epa->name);
        $this->assertSame('public_institution', $epa->type->value);
        $this->assertTrue($epa->isActive());
        $this->assertNotNull($epa->verified_at);
        $this->assertTrue($epa->settings['demo']);
        $this->assertCount(5, $epa->settings['locations']);
        $this->assertContains('Field Services', $epa->settings['departments']);
        $this->assertSame('field', $epa->settings['sample_directory']['kojo.appiah@epa.orbit-demo.test']['work_type']);
        $this->assertStringContainsString('Not live', $epa->settings['sample_metrics']['label']);

        $this->assertSame('educational_institution', $upsa->type->value);
        $this->assertSame('https://upsa.edu.gh/students/', $upsa->settings['public_reference']);
        $this->assertContains('Faculty of Accounting and Finance', $upsa->settings['departments']);
        $this->assertSame('student', $upsa->settings['sample_directory']['maame.owusu@upsa.orbit-demo.test']['work_type']);
        $this->assertSame('+233303000201', $upsa->contact_phone);

        $this->assertSame(10, User::query()->count());

        $expectations = [
            'platform.admin@orbit-demo.test' => ['platform_super_admin', null, 'platform.access'],
            'platform.support@orbit-demo.test' => ['platform_support', null, 'members.view'],
            'ama.mensah@epa.orbit-demo.test' => ['organization_admin', $epa->getKey(), 'members.invite'],
            'esi.quaye@epa.orbit-demo.test' => ['organization_manager', $epa->getKey(), 'members.view'],
            'kojo.appiah@epa.orbit-demo.test' => ['member', $epa->getKey(), null],
            'abena.darko@epa.orbit-demo.test' => ['member', $epa->getKey(), null],
            'akua.sarpong@upsa.orbit-demo.test' => ['organization_admin', $upsa->getKey(), 'organization.update'],
            'nana.yeboah@upsa.orbit-demo.test' => ['organization_manager', $upsa->getKey(), 'organization.view'],
            'selorm.adjei@upsa.orbit-demo.test' => ['member', $upsa->getKey(), null],
            'maame.owusu@upsa.orbit-demo.test' => ['student', $upsa->getKey(), null],
        ];

        foreach ($expectations as $email => [$role, $organizationId, $permission]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue($user->hasRole($role), $email);
            $this->assertSame($organizationId, $user->organization_id);
            $this->assertSame(UserStatus::Active, $user->status);
            $this->assertNotNull($user->email_verified_at);
            $this->assertNotNull($user->phone_verified_at);
            $this->assertNotNull($user->phone);
            $this->assertNotNull($user->terms_accepted_at);
            $this->assertSame(config('orbit.terms_version'), $user->terms_version);
            $this->assertNotNull($user->uuid);

            if ($permission !== null) {
                $this->assertTrue($user->hasPermissionTo($permission), $email);
            }
        }

        $this->assertFalse(User::query()->where('email', 'esi.quaye@epa.orbit-demo.test')->firstOrFail()->hasPermissionTo('members.invite'));
        $this->assertFalse(User::query()->where('email', 'maame.owusu@upsa.orbit-demo.test')->firstOrFail()->hasPermissionTo('members.manage'));
        $this->assertFalse(User::query()->where('email', 'kojo.appiah@epa.orbit-demo.test')->firstOrFail()->hasPermissionTo('platform.access'));
        $this->assertFalse(User::query()->where('email', 'platform.support@orbit-demo.test')->firstOrFail()->hasPermissionTo('members.manage'));

        DemoAccount::user([
            'first_name' => 'Ama',
            'last_name' => 'Mensah-Updated',
            'email' => 'ama.mensah@epa.orbit-demo.test',
            'phone' => '+233302000111',
        ], 'organization_admin', $epa);

        $this->assertSame(10, User::query()->count());
        $this->assertSame('Mensah-Updated', User::query()->where('email', 'ama.mensah@epa.orbit-demo.test')->firstOrFail()->last_name);

        $this->post(route('login.store'), [
            'email' => 'ama.mensah@epa.orbit-demo.test',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('app.dashboard', absolute: false));

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => 'maame.owusu@upsa.orbit-demo.test',
            'password' => self::PASSWORD,
        ])->assertRedirect(route('app.dashboard', absolute: false));
    }
}
