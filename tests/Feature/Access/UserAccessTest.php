<?php

namespace Tests\Feature\Access;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Organisation administrators manage roles, direct permissions, and status of
 * accounts in their own organisation, within strict checks and balances.
 */
class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_users_page_lists_only_own_organisation_accounts(): void
    {
        $admin = $this->createOrganizationAdmin();
        $colleague = $this->createMember($admin->organization, attributes: ['first_name' => 'Efua', 'last_name' => 'Colleague']);
        $stranger = $this->createMember(attributes: ['first_name' => 'Kwabena', 'last_name' => 'Stranger']);

        $this->actingAs($admin)
            ->get(route('org.users.index'))
            ->assertOk()
            ->assertSee('Efua Colleague')
            ->assertSee($colleague->email)
            ->assertDontSee('Kwabena Stranger');

        $this->actingAs($admin)->get(route('org.users.index', ['role' => 'member']))->assertOk()->assertSee('Efua Colleague');
        $this->actingAs($admin)->get(route('org.users.index', ['role' => 'organization_admin']))->assertOk()->assertDontSee('Efua Colleague');
    }

    #[Test]
    public function administrators_can_promote_and_demote_tenant_roles(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);

        $this->actingAs($admin)
            ->put(route('org.users.roles', $member), ['roles' => ['organization_manager']])
            ->assertRedirect(route('org.users.show', $member))
            ->assertSessionHas('status');

        $member->refresh();
        $this->assertTrue($member->hasRole('organization_manager'));
        $this->assertFalse($member->hasRole('member'));
        $this->assertTrue($member->can('users.view'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'user.roles_updated',
            'subject_id' => $member->getKey(),
            'causer_id' => $admin->getKey(),
            'organization_id' => $admin->organization_id,
        ]);
    }

    #[Test]
    public function administrators_cannot_grant_platform_roles_or_unknown_roles(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);

        $this->actingAs($admin)
            ->put(route('org.users.roles', $member), ['roles' => ['platform_super_admin']])
            ->assertSessionHasErrors('roles');

        $this->actingAs($admin)
            ->put(route('org.users.roles', $member), ['roles' => ['does_not_exist']])
            ->assertSessionHasErrors('roles.0');

        $this->assertTrue($member->fresh()->hasRole('member'));
        $this->assertFalse($member->fresh()->can('platform.access'));
    }

    #[Test]
    public function the_last_active_administrator_cannot_be_demoted_or_suspended(): void
    {
        $admin = $this->createOrganizationAdmin();

        // A manager who has been given `users.manage` directly can manage access
        // without holding the administrator role.
        $deputy = $this->createMember($admin->organization, role: 'organization_manager');
        $this->actingAs($admin)->put(route('org.users.permissions', $deputy), ['permissions' => ['users.manage']])->assertSessionHasNoErrors();
        $this->assertTrue($deputy->fresh()->can('users.manage'));

        $this->actingAs($deputy)->put(route('org.users.roles', $admin), ['roles' => ['member']])->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->hasRole('organization_admin'));

        $this->actingAs($deputy)->patch(route('org.users.status', $admin), ['status' => 'suspended'])->assertSessionHasErrors('status');
        $this->assertTrue($admin->fresh()->isActive());

        // Once a second active administrator exists, the first may be demoted.
        $this->createOrganizationAdmin($admin->organization);

        $this->actingAs($deputy)->put(route('org.users.roles', $admin), ['roles' => ['member']])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->hasRole('organization_admin'));

        // A suspended administrator does not count as cover for the remaining one.
        $remaining = User::query()->where('organization_id', $admin->organization_id)->role('organization_admin')->sole();
        $this->actingAs($deputy)->put(route('org.users.roles', $admin), ['roles' => ['organization_admin']])->assertSessionHasNoErrors();
        $this->actingAs($deputy)->patch(route('org.users.status', $admin), ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->actingAs($deputy)->patch(route('org.users.status', $remaining), ['status' => 'suspended'])->assertSessionHasErrors('status');
        $this->assertTrue($remaining->fresh()->isActive());
    }

    #[Test]
    public function direct_permissions_are_limited_to_what_the_administrator_holds(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);

        $this->actingAs($admin)
            ->put(route('org.users.permissions', $member), ['permissions' => ['staff.view', 'locations.view']])
            ->assertRedirect(route('org.users.show', $member));

        $member->refresh();
        $this->assertTrue($member->hasDirectPermission('staff.view'));
        $this->assertTrue($member->can('locations.view'));
        $this->assertDatabaseHas('activity_log', ['event' => 'user.permissions_updated', 'subject_id' => $member->getKey()]);

        // platform.* is never grantable from an organisation account.
        $this->actingAs($admin)
            ->put(route('org.users.permissions', $member), ['permissions' => ['platform.access']])
            ->assertSessionHasErrors('permissions');
        $this->assertFalse($member->fresh()->can('platform.access'));

        // Revoking works and is audited.
        $this->actingAs($admin)->put(route('org.users.permissions', $member), ['permissions' => []])->assertSessionHasNoErrors();
        $this->assertCount(0, $member->fresh()->permissions);
    }

    #[Test]
    public function managers_and_members_cannot_manage_access(): void
    {
        $admin = $this->createOrganizationAdmin();
        $manager = $this->createMember($admin->organization, role: 'organization_manager');
        $member = $this->createMember($admin->organization);

        $this->actingAs($manager)->get(route('org.users.index'))->assertOk()->assertDontSee('Save roles');
        $this->actingAs($manager)->put(route('org.users.roles', $member), ['roles' => ['organization_manager']])->assertForbidden();
        $this->actingAs($manager)->patch(route('org.users.status', $member), ['status' => 'suspended'])->assertForbidden();

        $this->actingAs($member)->get(route('org.users.index'))->assertForbidden();
        $this->actingAs($member)->get(route('org.users.show', $manager))->assertForbidden();
        $this->actingAs($member)->get(route('org.users.show', $member))->assertForbidden();

        $this->assertTrue($member->fresh()->hasRole('member'));
    }

    #[Test]
    public function an_administrator_cannot_change_their_own_access(): void
    {
        $admin = $this->createOrganizationAdmin();
        $this->createOrganizationAdmin($admin->organization);

        $this->actingAs($admin)->get(route('org.users.show', $admin))->assertOk()->assertSee('You cannot change your own access');
        $this->actingAs($admin)->put(route('org.users.roles', $admin), ['roles' => ['member']])->assertForbidden();
        $this->actingAs($admin)->put(route('org.users.permissions', $admin), ['permissions' => []])->assertForbidden();
        $this->actingAs($admin)->patch(route('org.users.status', $admin), ['status' => 'suspended'])->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole('organization_admin'));
    }

    #[Test]
    public function suspension_signs_the_account_out_and_blocks_sign_in_until_reactivated(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);

        // Simulate a live database session for the member.
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert([
            'id' => 'member-session',
            'user_id' => $member->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($admin)
            ->patch(route('org.users.status', $member), ['status' => 'suspended', 'reason' => 'Contract ended'])
            ->assertRedirect(route('org.users.show', $member));

        $this->assertFalse($member->fresh()->isActive());
        $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
        $this->assertDatabaseHas('activity_log', ['event' => 'user.status_updated', 'subject_id' => $member->getKey()]);

        // The suspended account cannot sign in (guard state is asserted, not the session store).
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $member->email, 'password' => 'password']);
        $this->assertGuest();

        $this->actingAs($admin)->patch(route('org.users.status', $member), ['status' => 'active'])->assertRedirect(route('org.users.show', $member));
        $this->assertTrue($member->fresh()->isActive());
    }

    #[Test]
    public function the_organisation_roles_page_is_read_only_and_shows_tenant_roles(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->get(route('org.roles.index'))
            ->assertOk()
            ->assertSee('Organisation administrator')
            ->assertSee('Organisation manager')
            ->assertSee('users.manage')
            ->assertDontSee('Platform super administrator')
            ->assertDontSee('platform.access');

        $this->actingAs($this->createMember($admin->organization))->get(route('org.roles.index'))->assertForbidden();
    }

    #[Test]
    public function the_profile_page_shows_roles_permissions_and_sign_ins(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);

        $this->actingAs($admin)
            ->get(route('app.profile'))
            ->assertOk()
            ->assertSee('Organisation administrator')
            ->assertSee('users.manage')
            ->assertSee('Recent sign-ins');
    }

    #[Test]
    public function a_suspended_organisation_hides_its_accounts_from_tenant_administration(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createOrganizationAdmin($organization);
        $member = $this->createMember($organization);

        $organization->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($admin)->put(route('org.users.roles', $member), ['roles' => ['organization_manager']])->assertRedirect(route('login'));
        $this->assertTrue($member->fresh()->hasRole('member'));
    }
}
