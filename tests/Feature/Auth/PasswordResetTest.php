<?php

namespace Tests\Feature\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_link_request_page_renders(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    #[Test]
    public function reset_link_is_sent_for_existing_accounts(): void
    {
        Notification::fake();
        $user = $this->createOrganizationAdmin();

        $this->post(route('password.email'), ['email' => strtoupper($user->email)])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function unknown_email_receives_the_same_generic_response(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors();

        Notification::assertNothingSent();
    }

    #[Test]
    public function password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = $this->createOrganizationAdmin();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();

            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NewSecretPass99',
                'password_confirmation' => 'NewSecretPass99',
            ])->assertRedirect(route('login'))->assertSessionHas('status');

            $this->assertTrue(Hash::check('NewSecretPass99', $user->fresh()->password));

            // Token is single-use.
            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'AnotherSecret77',
                'password_confirmation' => 'AnotherSecret77',
            ])->assertSessionHasErrors('email');

            return true;
        });
    }

    #[Test]
    public function invalid_token_is_rejected(): void
    {
        $user = $this->createOrganizationAdmin();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewSecretPass99',
            'password_confirmation' => 'NewSecretPass99',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    #[Test]
    public function reset_link_requests_are_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertStatus(429);
    }
}
