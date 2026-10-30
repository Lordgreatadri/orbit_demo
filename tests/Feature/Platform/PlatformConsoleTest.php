<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->platform()->create();
        $user->assignRole('platform_super_admin');

        return $user;
    }

    private function support(): User
    {
        $user = User::factory()->platform()->create();
        $user->assignRole('platform_support');

        return $user;
    }

    #[Test]
    public function the_platform_dashboard_shows_cross_tenant_aggregates(): void
    {
        Organization::factory()->count(2)->create();
        Organization::factory()->suspended()->create();
        $this->createMember();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform overview')
            ->assertSee('Organisations')
            ->assertSee('Tenant health')
            ->assertSee('Suspended');

        $this->actingAs($this->support())->get(route('admin.dashboard'))->assertOk()->assertDontSee('New platform account');
    }

    #[Test]
    public function platform_accounts_without_console_access_land_on_their_profile(): void
    {
        $user = User::factory()->platform()->create();

        $this->actingAs($user)->get(route('app.dashboard'))->assertRedirect(route('app.profile'));
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    #[Test]
    public function organisations_can_be_listed_filtered_and_inspected(): void
    {
        $admin = $this->createOrganizationAdmin(Organization::factory()->create(['name' => 'Fictional Water Authority']));
        Organization::factory()->create(['name' => 'Another Institute']);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.organizations.index', ['q' => 'water']))
            ->assertOk()
            ->assertSee('Fictional Water Authority')
            ->assertDontSee('Another Institute');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.organizations.show', $admin->organization))
            ->assertOk()
            ->assertSee('Fictional Water Authority')
            ->assertSee($admin->email)
            ->assertSee('Change status');

        $this->assertDatabaseHas('activity_log', ['event' => 'platform.organization.viewed', 'organization_id' => $admin->organization_id]);

        // Support can view but not change status.
        $this->actingAs($this->support())->get(route('admin.organizations.show', $admin->organization))->assertOk()->assertDontSee('Change status');
        $this->actingAs($this->support())
            ->patch(route('admin.organizations.status', $admin->organization), ['status' => 'suspended', 'reason' => 'Testing refusal'])
            ->assertForbidden();
        $this->assertTrue($admin->organization->fresh()->isActive());
    }

    #[Test]
    public function suspending_an_organisation_signs_out_its_accounts_and_is_audited(): void
    {
        $admin = $this->createOrganizationAdmin();
        $superAdmin = $this->superAdmin();

        // Validation: reason required, pending status not settable.
        $this->actingAs($superAdmin)->patch(route('admin.organizations.status', $admin->organization), ['status' => 'suspended'])->assertSessionHasErrors('reason');
        $this->actingAs($superAdmin)->patch(route('admin.organizations.status', $admin->organization), ['status' => 'pending_verification', 'reason' => 'Nope nope'])->assertSessionHasErrors('status');
        $this->assertTrue($admin->organization->fresh()->isActive());

        // Use the database session driver for this request so the sign-out side effect is observable.
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'tenant-session',
            'user_id' => $admin->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($superAdmin)
            ->patch(route('admin.organizations.status', $admin->organization), ['status' => 'suspended', 'reason' => 'Unpaid invoices (fictional)'])
            ->assertRedirect(route('admin.organizations.show', $admin->organization))
            ->assertSessionHas('status');

        $this->assertSame('suspended', $admin->organization->fresh()->status->value);
        $this->assertDatabaseMissing('sessions', ['id' => 'tenant-session']);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'platform.organization.status_updated',
            'organization_id' => $admin->organization_id,
            'causer_id' => $superAdmin->getKey(),
        ]);

        $this->actingAs($superAdmin)->patch(route('admin.organizations.status', $admin->organization), ['status' => 'active', 'reason' => 'Resolved (fictional)'])->assertRedirect();
        $this->assertTrue($admin->organization->fresh()->isActive());
    }

    #[Test]
    public function super_admins_can_provision_platform_accounts_and_manage_any_users_access(): void
    {
        Notification::fake();
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->get(route('admin.users.create'))->assertOk()->assertSee('Platform role');

        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'first_name' => 'Kofi',
            'last_name' => 'Support',
            'email' => 'Kofi.Support@Example.test',
            'role' => 'platform_support',
        ])->assertRedirect();

        $created = User::query()->where('email', 'kofi.support@example.test')->sole();
        $this->assertNull($created->organization_id);
        $this->assertTrue($created->hasRole('platform_support'));
        $this->assertTrue($created->can('platform.access'));
        $this->assertDatabaseHas('activity_log', ['event' => 'platform.user.created', 'subject_id' => $created->getKey()]);
        Notification::assertSentTo($created, ResetPassword::class);

        // Tenant roles cannot be given to the new platform account.
        $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'first_name' => 'Bad', 'last_name' => 'Role', 'email' => 'bad.role@example.test', 'role' => 'organization_admin',
        ])->assertSessionHasErrors('role');

        // Platform roles only to platform accounts; tenant accounts get tenant roles.
        $member = $this->createMember();
        $this->actingAs($superAdmin)->put(route('admin.users.roles', $member), ['roles' => ['platform_support']])->assertSessionHasErrors('roles');
        $this->actingAs($superAdmin)->put(route('admin.users.roles', $member), ['roles' => ['organization_admin']])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->hasRole('organization_admin'));

        $this->actingAs($superAdmin)->put(route('admin.users.permissions', $created), ['permissions' => ['platform.audit.view']])->assertSessionHasNoErrors();
        $this->assertTrue($created->fresh()->hasDirectPermission('platform.audit.view'));
        $this->actingAs($superAdmin)->put(route('admin.users.permissions', $created), ['permissions' => ['staff.view']])->assertSessionHasErrors('permissions');

        $this->actingAs($superAdmin)->patch(route('admin.users.status', $created), ['status' => 'suspended', 'reason' => 'Offboarded'])->assertSessionHasNoErrors();
        $this->assertFalse($created->fresh()->isActive());
        $this->actingAs($superAdmin)->patch(route('admin.users.status', $created), ['status' => 'active'])->assertSessionHasNoErrors();

        // The last active platform super administrator is protected, even from a
        // platform account that holds platform.users.manage directly.
        $this->actingAs($superAdmin)->put(route('admin.users.permissions', $created), ['permissions' => ['platform.users.manage']])->assertSessionHasNoErrors();
        // Provisioned accounts still go through e-mail/phone verification on first sign-in.
        $operator = $created->fresh()->forceFill(['email_verified_at' => now(), 'phone_verified_at' => now()]);
        $operator->save();
        $this->assertTrue($operator->can('platform.users.manage'));

        $this->actingAs($operator)->patch(route('admin.users.status', $superAdmin), ['status' => 'suspended'])->assertSessionHasErrors('status');
        $this->actingAs($operator)->put(route('admin.users.roles', $superAdmin), ['roles' => ['platform_support']])->assertSessionHasNoErrors();
        $this->assertTrue($superAdmin->fresh()->isActive());
        $this->assertTrue($superAdmin->fresh()->hasRole('platform_super_admin'));

        // platform.users.manage is not enough to mint another super administrator.
        $this->actingAs($operator)->post(route('admin.users.store'), [
            'first_name' => 'New',
            'last_name' => 'Super',
            'email' => 'new.super@example.test',
            'role' => 'platform_super_admin',
        ])->assertSessionHasErrors('role');
        $this->assertNull(User::query()->where('email', 'new.super@example.test')->first());

        $peer = User::factory()->platform()->create();
        $peer->assignRole('platform_support');
        $this->actingAs($operator)->put(route('admin.users.roles', $peer), ['roles' => ['platform_super_admin']])->assertSessionHasErrors('roles');
        $this->assertFalse($peer->fresh()->hasRole('platform_super_admin'));
    }

    #[Test]
    public function support_staff_can_only_read(): void
    {
        $support = $this->support();
        $member = $this->createMember();

        $this->actingAs($support)->get(route('admin.users.index'))->assertOk()->assertSee($member->email);
        $this->actingAs($support)->get(route('admin.users.show', $member))->assertOk()->assertDontSee('Save roles');
        $this->actingAs($support)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($support)->post(route('admin.users.store'), ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@example.test', 'role' => 'platform_support'])->assertForbidden();
        $this->actingAs($support)->put(route('admin.users.roles', $member), ['roles' => ['organization_admin']])->assertForbidden();
        $this->actingAs($support)->get(route('admin.roles.index'))->assertOk()->assertDontSee('New role');
        $this->actingAs($support)->get(route('admin.roles.create'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.permissions.index'))->assertOk()->assertDontSee('New permission');

        $this->assertTrue($member->fresh()->hasRole('member'));
    }

    #[Test]
    public function platform_audit_pages_span_every_organisation_and_can_be_filtered(): void
    {
        $adminA = $this->createOrganizationAdmin();
        $adminB = $this->createOrganizationAdmin();

        $this->post(route('login.store'), ['email' => $adminA->email, 'password' => 'password']);
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $adminB->email, 'password' => 'wrong']);

        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)
            ->get(route('admin.audit.logins'))
            ->assertOk()
            ->assertSee($adminA->email)
            ->assertSee($adminB->email)
            ->assertSee($adminA->organization->name);

        $this->actingAs($superAdmin)
            ->get(route('admin.audit.logins', ['organization' => $adminA->organization->uuid]))
            ->assertOk()
            ->assertSee($adminA->email)
            ->assertDontSee($adminB->email);

        $this->actingAs($superAdmin)
            ->get(route('admin.audit.logins', ['event' => 'failed']))
            ->assertOk()
            ->assertSee($adminB->email)
            ->assertDontSee($adminA->email);

        // The platform's own actions appear in the platform audit trail.
        $this->actingAs($superAdmin)->get(route('admin.users.index'));
        $this->actingAs($superAdmin)
            ->get(route('admin.audit.index', ['organization' => 'platform']))
            ->assertOk()
            ->assertSee('platform.users.viewed');
    }

    #[Test]
    public function planned_platform_modules_render_as_labelled_placeholders(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('coming soon')
            ->assertSee('Planned module');

        $this->actingAs($this->support())->get(route('admin.settings.index'))->assertForbidden();
    }
}
