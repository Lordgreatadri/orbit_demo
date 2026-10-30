<?php

namespace Tests\Feature\Shell;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sidebar navigation, section visibility by role, and planned-module pages.
 */
class WorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_sidebar_is_fixed_and_shows_sections_for_the_organisation_administrator(): void
    {
        $admin = $this->createOrganizationAdmin();

        $response = $this->actingAs($admin)->get(route('org.dashboard'))->assertOk();

        $response->assertSee('fixed inset-y-0 left-0', false)
            ->assertSee('overflow-y-auto', false)
            ->assertSee('Overview')
            ->assertSee('People')
            ->assertSee('Staff register')
            ->assertSee('Users &amp; access', false)
            ->assertSee('Attendance')
            ->assertSee('Workforce')
            ->assertSee('Insights')
            ->assertSee('Administration')
            ->assertSee('Soon')
            ->assertSee('aria-expanded', false)
            ->assertSee('Open navigation');
    }

    #[Test]
    public function navigation_is_filtered_by_permission(): void
    {
        $member = $this->createMember();
        $labels = collect(Navigation::labels($member));

        $this->assertTrue($labels->contains('Dashboard'));
        $this->assertFalse($labels->contains('Staff register'));
        $this->assertFalse($labels->contains('Users & access'));
        $this->assertFalse($labels->contains('Organisation settings'));
        $this->assertTrue($labels->contains('Schedules'));

        $this->actingAs($member)->get(route('org.dashboard'))->assertOk()->assertDontSee('Staff register')->assertDontSee('Audit &amp; security', false);

        $platform = User::factory()->platform()->create();
        $platform->assignRole('platform_super_admin');
        $platformLabels = collect(Navigation::labels($platform));

        $this->assertTrue($platformLabels->contains('Organisations'));
        $this->assertTrue($platformLabels->contains('Roles'));
        $this->assertFalse($platformLabels->contains('Staff register'));
    }

    #[Test]
    public function planned_modules_render_labelled_placeholders_and_respect_permissions(): void
    {
        $admin = $this->createOrganizationAdmin();

        foreach (['org.attendance.index', 'org.attendance.sessions', 'org.field.index', 'org.schedules.index', 'org.leave.index', 'org.notifications.index', 'org.analytics.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()->assertSee('coming soon')->assertSee('Planned module');
        }

        $member = $this->createMember($admin->organization);
        $this->actingAs($member)->get(route('org.schedules.index'))->assertOk();
        $this->actingAs($member)->get(route('org.analytics.index'))->assertForbidden();
    }

    #[Test]
    public function the_organisation_audit_trail_and_sign_in_history_are_separate_pages(): void
    {
        $admin = $this->createOrganizationAdmin();
        $manager = $this->createMember($admin->organization, role: 'organization_manager');

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('logout'));

        $this->actingAs($admin)->post(route('org.departments.store'), ['name' => 'Audit Dept']);

        $this->actingAs($admin)
            ->get(route('org.audit.index'))
            ->assertOk()
            ->assertSee('department.created')
            ->assertDontSee('auth.login');

        $this->actingAs($admin)
            ->get(route('org.audit.logins'))
            ->assertOk()
            ->assertSee('Sign-in history')
            ->assertSee($admin->email)
            ->assertSee('Sign-ins today');

        // Other organisations' events never appear.
        $stranger = $this->createOrganizationAdmin();
        $this->post(route('login.store'), ['email' => $stranger->email, 'password' => 'wrong']);
        $this->actingAs($admin)->get(route('org.audit.logins'))->assertOk()->assertDontSee($stranger->email);

        $this->actingAs($manager)->get(route('org.audit.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('org.audit.logins'))->assertForbidden();
    }
}
