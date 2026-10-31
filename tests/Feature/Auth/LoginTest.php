<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_screen_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    #[Test]
    public function users_can_authenticate_with_valid_credentials(): void
    {
        $user = $this->createOrganizationAdmin();

        $response = $this->fromTenant($user->organization)->post(route('login.store'), [
            'email' => strtoupper($user->email),
            'password' => 'password',
        ]);

        $response->assertRedirect(route('app.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);

        // Sign-in history lives in login_events, not in the audit trail.
        $this->assertDatabaseHas('login_events', [
            'event' => 'login',
            'user_id' => $user->getKey(),
            'organization_id' => $user->organization_id,
            'email' => $user->email,
        ]);
        $this->assertDatabaseMissing('activity_log', ['event' => 'auth.login']);
    }

    #[Test]
    public function failed_attempts_and_lockouts_are_recorded_in_login_history(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->postLogin($user, 'wrong-password');

        $this->assertDatabaseHas('login_events', ['event' => 'failed', 'user_id' => $user->getKey(), 'email' => $user->email]);

        $this->post(route('login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong-password']);

        $this->assertDatabaseHas('login_events', ['event' => 'failed', 'user_id' => null, 'organization_id' => null, 'email' => 'nobody@example.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postLogin($user, 'wrong-password');
        }

        $this->assertDatabaseHas('login_events', [
            'event' => 'lockout',
            'email' => $user->email,
            'user_id' => $user->getKey(),
            'organization_id' => $user->organization_id,
        ]);
        $this->assertDatabaseMissing('login_events', ['event' => 'login']);
    }

    #[Test]
    public function invalid_password_is_rejected_with_a_generic_message(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->fromTenant($user->organization)
            ->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function unknown_email_and_wrong_password_produce_the_same_error(): void
    {
        $user = $this->createOrganizationAdmin();

        $generic = __('auth.failed');

        $this->fromTenant($user->organization)
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => $generic]);

        $this->fromTenant($user->organization)
            ->post(route('login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => $generic]);

        // Neither variant leaks whether the address is registered.
        $this->assertStringNotContainsStringIgnoringCase('not found', $generic);
        $this->assertStringNotContainsStringIgnoringCase('password', $generic);
    }

    #[Test]
    public function login_is_locked_after_too_many_failed_attempts(): void
    {
        $user = $this->createOrganizationAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->postLogin($user, 'wrong-password');
        }

        $this->postLogin($user)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function suspended_users_cannot_sign_in(): void
    {
        $user = User::factory()->suspended()->create();

        $this->postLogin($user)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function users_of_suspended_organisations_cannot_sign_in(): void
    {
        $organization = Organization::factory()->suspended()->create();
        $user = $this->createOrganizationAdmin($organization);

        $this->postLogin($user)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function users_of_organisations_suspended_mid_session_are_signed_out(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createOrganizationAdmin($organization);

        $this->actingAs($user)->get(route('org.dashboard'))->assertOk();

        $organization->forceFill(['status' => 'suspended'])->save();

        $this->get(route('org.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function users_can_log_out(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('login_events', ['event' => 'logout', 'user_id' => $user->getKey()]);
    }

    #[Test]
    public function a_correct_password_on_the_wrong_workspace_is_a_failed_attempt(): void
    {
        $epa = Organization::factory()->create(['name' => 'EPA Demo']);
        $mcc = Organization::factory()->create(['name' => 'Mobile Content Ltd']);
        $epaAdmin = $this->createOrganizationAdmin($epa);

        $this->fromTenant($mcc)
            ->from(route('login'))
            ->post(route('login.store'), ['email' => $epaAdmin->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', [
            'event' => 'failed',
            'user_id' => $epaAdmin->getKey(),
            'email' => $epaAdmin->email,
        ]);
        $this->assertDatabaseMissing('login_events', ['event' => 'login']);
        $this->assertDatabaseMissing('login_events', ['event' => 'logout']);
    }

    #[Test]
    public function an_organisation_member_cannot_sign_in_on_the_central_host(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->fromCentral()
            ->from(route('login'))
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_events', [
            'event' => 'failed',
            'user_id' => $user->getKey(),
            'email' => $user->email,
        ]);
        $this->assertDatabaseMissing('login_events', ['event' => 'login']);
    }

    #[Test]
    public function guests_are_redirected_from_protected_pages(): void
    {
        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
        $this->get(route('org.dashboard'))->assertRedirect(route('login'));
        $this->get(route('org.users.index'))->assertRedirect(route('login'));
        $this->post(route('org.invitations.store'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }
}
