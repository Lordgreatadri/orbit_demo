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

        $response = $this->post(route('login.store'), [
            'email' => strtoupper($user->email),
            'password' => 'password',
        ]);

        $response->assertRedirect(route('app.dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.login', 'causer_id' => $user->getKey()]);
    }

    #[Test]
    public function invalid_password_is_rejected_with_a_generic_message(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->from(route('login'))
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

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => $generic]);

        $this->post(route('login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
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
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function suspended_users_cannot_sign_in(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function users_of_suspended_organisations_cannot_sign_in(): void
    {
        $organization = Organization::factory()->suspended()->create();
        $user = $this->createOrganizationAdmin($organization);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function users_of_organisations_suspended_mid_session_are_signed_out(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createOrganizationAdmin($organization);

        $this->actingAs($user)->get(route('app.dashboard'))->assertOk();

        $organization->forceFill(['status' => 'suspended'])->save();

        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function users_can_log_out(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    #[Test]
    public function guests_are_redirected_from_protected_pages(): void
    {
        $this->get(route('app.dashboard'))->assertRedirect(route('login'));
        $this->get(route('app.members.index'))->assertRedirect(route('login'));
        $this->post(route('app.invitations.store'))->assertRedirect(route('login'));
    }
}
