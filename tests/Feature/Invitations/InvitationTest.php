<?php

namespace Tests\Feature\Invitations;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function acceptPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Yaw',
            'last_name' => 'Asare',
            'phone' => '+233244000000',
            'password' => 'MemberSecret42',
            'password_confirmation' => 'MemberSecret42',
            'terms' => '1',
        ], $overrides);
    }

    #[Test]
    public function administrator_can_invite_a_member_into_their_own_organisation(): void
    {
        Notification::fake();
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->post(route('app.invitations.store'), ['email' => 'New.Member@Example.test', 'role' => 'member'])
            ->assertRedirect(route('app.members.index'))
            ->assertSessionHas('status');

        $invitation = Invitation::withoutTenantScope()->sole();
        $this->assertSame($admin->organization_id, $invitation->organization_id);
        $this->assertSame('new.member@example.test', $invitation->email);
        $this->assertSame('member', $invitation->role);
        $this->assertSame($admin->getKey(), $invitation->invited_by_id);
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertTrue($invitation->expires_at->isFuture());

        Notification::assertSentOnDemand(
            InvitationNotification::class,
            function (InvitationNotification $n, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
                $this->assertSame('new.member@example.test', $notifiable->routes['mail']);
                $this->assertSame($invitation->token_hash, Invitation::hashToken($n->rawToken));
                $this->assertStringContainsString('/invitations/'.$n->rawToken, $n->acceptUrl());

                return true;
            }
        );

        $this->assertDatabaseHas('activity_log', ['event' => 'invitation.created', 'organization_id' => $admin->organization_id]);
    }

    #[Test]
    public function tenant_cannot_be_overridden_by_client_supplied_organisation_id(): void
    {
        Notification::fake();
        $admin = $this->createOrganizationAdmin();
        $other = Organization::factory()->create();

        $this->actingAs($admin)->post(route('app.invitations.store'), [
            'email' => 'victim@example.test',
            'role' => 'member',
            'organization_id' => $other->getKey(),
        ])->assertRedirect();

        $this->assertSame($admin->organization_id, Invitation::withoutTenantScope()->sole()->organization_id);
    }

    #[Test]
    public function members_and_managers_cannot_invite(): void
    {
        Notification::fake();

        $member = $this->createMember();
        $this->actingAs($member)->post(route('app.invitations.store'), ['email' => 'x@example.test', 'role' => 'member'])->assertForbidden();

        $manager = $this->createMember(role: 'organization_manager');
        $this->actingAs($manager)->post(route('app.invitations.store'), ['email' => 'y@example.test', 'role' => 'member'])->assertForbidden();

        $this->assertDatabaseCount('invitations', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function platform_roles_cannot_be_granted_by_invitation(): void
    {
        Notification::fake();
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->post(route('app.invitations.store'), ['email' => 'x@example.test', 'role' => 'platform_super_admin'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('invitations', 0);
    }

    #[Test]
    public function duplicate_pending_invitation_and_existing_account_are_rejected(): void
    {
        Notification::fake();
        $admin = $this->createOrganizationAdmin();
        $existing = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('app.invitations.store'), ['email' => $existing->email, 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)->post(route('app.invitations.store'), ['email' => 'pending@example.test', 'role' => 'member']);
        $this->actingAs($admin)
            ->post(route('app.invitations.store'), ['email' => 'pending@example.test', 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('invitations', 1);
    }

    #[Test]
    public function invitations_cannot_be_sent_until_the_organisation_is_active(): void
    {
        $organization = Organization::factory()->pendingVerification()->create();
        $admin = $this->createOrganizationAdmin($organization);

        // Bypass the verified middleware concern by verifying the user but not the org.
        $this->actingAs($admin)->post(route('app.invitations.store'), ['email' => 'x@example.test', 'role' => 'member'])->assertForbidden();
    }

    #[Test]
    public function invitee_can_accept_a_valid_invitation_and_joins_the_right_organisation_with_the_right_role(): void
    {
        $organization = Organization::factory()->create([
            'name' => "O'Connell Institute of Testing",
        ]);
        $admin = $this->createOrganizationAdmin($organization);
        $raw = str_repeat('a', 64);
        $invitation = Invitation::factory()->invitedBy($admin)->withRawToken($raw)->create([
            'email' => 'yaw.asare@example.test',
            'role' => 'organization_manager',
        ]);

        $this->get(route('invitations.show', ['token' => $raw]))
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee('yaw.asare@example.test')
            ->assertSee('Organisation manager');

        $this->post(route('invitations.accept', ['token' => $raw]), $this->acceptPayload())
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'yaw.asare@example.test')->sole();
        $this->assertSame($organization->getKey(), $user->organization_id);
        $this->assertTrue($user->hasRole('organization_manager'));
        $this->assertFalse($user->hasRole('organization_admin'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->terms_accepted_at);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($user->getKey(), $invitation->accepted_user_id);

        $this->assertDatabaseHas('activity_log', ['event' => 'invitation.accepted', 'organization_id' => $organization->getKey()]);
    }

    #[Test]
    public function accepted_expired_revoked_and_unknown_tokens_are_rejected(): void
    {
        $organization = Organization::factory()->create();

        $cases = [
            'accepted' => Invitation::factory()->forOrganization($organization)->accepted()->withRawToken(str_repeat('b', 64))->create(),
            'expired' => Invitation::factory()->forOrganization($organization)->expired()->withRawToken(str_repeat('c', 64))->create(),
            'revoked' => Invitation::factory()->forOrganization($organization)->revoked()->withRawToken(str_repeat('d', 64))->create(),
        ];

        foreach (['b', 'c', 'd', 'z'] as $char) {
            $raw = str_repeat($char, 64);

            $this->get(route('invitations.show', ['token' => $raw]))->assertOk()->assertSee('This invitation is not valid');
            $this->post(route('invitations.accept', ['token' => $raw]), $this->acceptPayload())->assertOk()->assertSee('This invitation is not valid');
        }

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertNull($cases['expired']->fresh()->accepted_at);
    }

    #[Test]
    public function an_invitation_can_only_be_used_once(): void
    {
        $organization = Organization::factory()->create();
        $raw = str_repeat('e', 64);
        Invitation::factory()->forOrganization($organization)->withRawToken($raw)->create(['email' => 'once@example.test']);

        $this->post(route('invitations.accept', ['token' => $raw]), $this->acceptPayload())->assertRedirect(route('app.dashboard'));
        auth()->logout();

        $this->post(route('invitations.accept', ['token' => $raw]), $this->acceptPayload())->assertOk()->assertSee('This invitation is not valid');
        $this->assertSame(1, User::query()->where('email', 'once@example.test')->count());
    }

    #[Test]
    public function acceptance_validation_failures_create_no_account(): void
    {
        $raw = str_repeat('f', 64);
        Invitation::factory()->withRawToken($raw)->create(['email' => 'strict@example.test']);

        $this->from(route('invitations.show', ['token' => $raw]))
            ->post(route('invitations.accept', ['token' => $raw]), $this->acceptPayload([
                'first_name' => '',
                'password' => 'short',
                'password_confirmation' => 'short',
                'terms' => '',
            ]))
            ->assertRedirect(route('invitations.show', ['token' => $raw]))
            ->assertSessionHasErrors(['first_name', 'password', 'terms']);

        $this->assertDatabaseMissing('users', ['email' => 'strict@example.test']);
        $this->assertGuest();
    }

    #[Test]
    public function administrator_can_revoke_own_pending_invitation_but_not_another_tenants(): void
    {
        $admin = $this->createOrganizationAdmin();
        $own = Invitation::factory()->invitedBy($admin)->create();
        $foreign = Invitation::factory()->create(); // another organisation

        $this->actingAs($admin)
            ->delete(route('app.invitations.destroy', $own))
            ->assertRedirect(route('app.members.index'));
        $this->assertNotNull($own->fresh()->revoked_at);

        // Tenant-scoped route binding: the foreign invitation does not resolve.
        $this->actingAs($admin)
            ->delete(route('app.invitations.destroy', $foreign))
            ->assertNotFound();
        $this->assertNull(Invitation::withoutTenantScope()->find($foreign->getKey())->revoked_at);
    }

    #[Test]
    public function members_page_lists_only_own_organisation_members_and_invitations(): void
    {
        $admin = $this->createOrganizationAdmin();
        $colleague = $this->createMember($admin->organization, attributes: ['first_name' => 'Efua', 'last_name' => 'Colleague']);
        $stranger = $this->createMember(attributes: ['first_name' => 'Kwabena', 'last_name' => 'Stranger']);

        Invitation::factory()->invitedBy($admin)->create(['email' => 'own-invite@example.test']);
        Invitation::factory()->create(['email' => 'foreign-invite@example.test']);

        $this->actingAs($admin)
            ->get(route('app.members.index'))
            ->assertOk()
            ->assertSee('Efua Colleague')
            ->assertDontSee('Kwabena Stranger')
            ->assertSee('own-invite@example.test')
            ->assertDontSee('foreign-invite@example.test');
    }

    #[Test]
    public function invitation_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('invitations.show', ['token' => 'nope']))->assertOk();
        }

        $this->get(route('invitations.show', ['token' => 'nope']))->assertStatus(429);
    }

    #[Test]
    public function authenticated_users_are_redirected_away_from_invitation_links(): void
    {
        $admin = $this->createOrganizationAdmin();
        $raw = str_repeat('g', 64);
        Invitation::factory()->withRawToken($raw)->create();

        $this->actingAs($admin)->get(route('invitations.show', ['token' => $raw]))->assertRedirect(route('app.dashboard'));
    }
}
