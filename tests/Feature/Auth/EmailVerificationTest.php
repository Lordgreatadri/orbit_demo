<?php

namespace Tests\Feature\Auth;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unverified_users_are_sent_to_the_verification_notice(): void
    {
        $organization = Organization::factory()->pendingVerification()->create();
        $user = User::factory()->forOrganization($organization)->unverified()->withRole('organization_admin')->create();

        $this->actingAs($user)->get(route('app.dashboard'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee('Verify your e-mail address');
    }

    #[Test]
    public function verifying_the_administrator_activates_the_organisation(): void
    {
        Event::fake([Verified::class]);

        $organization = Organization::factory()->pendingVerification()->create();
        $user = User::factory()->forOrganization($organization)->unverified()->withRole('organization_admin')->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('app.dashboard', absolute: false).'?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    #[Test]
    public function the_verified_listener_marks_the_organisation_active(): void
    {
        $organization = Organization::factory()->pendingVerification()->create();
        $user = User::factory()->forOrganization($organization)->unverified()->withRole('organization_admin')->create();

        $user->markEmailAsVerified();
        event(new Verified($user));

        $organization->refresh();
        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertNotNull($organization->verified_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'organization.verified', 'organization_id' => $organization->getKey()]);
    }

    #[Test]
    public function verifying_a_regular_member_does_not_activate_the_organisation(): void
    {
        $organization = Organization::factory()->pendingVerification()->create();
        $member = User::factory()->forOrganization($organization)->unverified()->withRole('member')->create();

        $member->markEmailAsVerified();
        event(new Verified($member));

        $this->assertSame(OrganizationStatus::PendingVerification, $organization->fresh()->status);
    }

    #[Test]
    public function invalid_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1('wrong@example.test'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function verification_email_can_be_resent(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect()->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
