<?php

namespace Tests\Feature\Auth;

use App\Enums\OrganizationStatus;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registration_page_renders(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Register your organisation')
            ->assertSee('Responsible contact')
            ->assertSee('do not register here');
    }

    #[Test]
    public function responsible_contact_registers_an_organisation_and_becomes_its_administrator(): void
    {
        Notification::fake();

        $response = $this->post(route('register.store'), $this->validRegistrationPayload([
            'email' => 'Ama.Mensah@Example.test',
            'phone' => '+233 20 123 4567',
        ]));

        $response->assertRedirect(route('app.dashboard'));
        $this->assertAuthenticated();

        $organization = Organization::query()->sole();
        $user = User::query()->sole();

        $this->assertSame('Fictional Institute of Testing', $organization->name);
        $this->assertSame(OrganizationStatus::PendingVerification, $organization->status);
        $this->assertSame('Africa/Accra', $organization->timezone);
        $this->assertSame('ama.mensah@example.test', $organization->contact_email);
        $this->assertNotNull($organization->uuid);
        $this->assertNotEmpty($organization->slug);
        $this->assertNotNull($organization->primaryDomain());
        $this->assertTrue($organization->primaryDomain()->is_verified);
        $this->assertTrue($organization->domains()->where('domain', $organization->primaryHost())->exists());

        $this->assertSame($organization->getKey(), $user->organization_id);
        $this->assertSame('ama.mensah@example.test', $user->email);
        $this->assertSame('+233201234567', $user->phone);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->actingAs($user)->get(route('app.dashboard'))->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('orbit.terms_version'), $user->terms_version);
        $this->assertTrue($user->hasRole('organization_admin'));
        $this->assertTrue($user->can('organization.update'));
        $this->assertTrue($user->can('users.invite'));

        $this->assertDatabaseHas('organization_domains', [
            'organization_id' => $organization->getKey(),
            'is_primary' => true,
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('organization_brandings', [
            'organization_id' => $organization->getKey(),
        ]);

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'organization.registered',
            'organization_id' => $organization->getKey(),
            'causer_id' => $user->getKey(),
        ]);
        $this->assertSame(1, Activity::query()->where('event', 'organization.registered')->count());
    }

    #[Test]
    public function organisation_is_active_immediately_when_verification_is_disabled(): void
    {
        config(['orbit.require_email_verification' => false]);
        Notification::fake();

        $this->post(route('register.store'), $this->validRegistrationPayload())->assertRedirect();

        $this->assertSame(OrganizationStatus::Active, Organization::query()->sole()->status);
    }

    #[Test]
    public function validation_failure_leaves_no_partial_records(): void
    {
        $this->from(route('register'))
            ->post(route('register.store'), $this->validRegistrationPayload([
                'organization_name' => '',
                'organization_type' => 'galaxy',
                'timezone' => 'Mars/Olympus',
                'email' => 'nope',
                'phone' => 'call me',
                'password' => 'short',
                'password_confirmation' => 'different',
                'terms' => '',
            ]))
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors([
                'organization_name', 'organization_type', 'timezone', 'email', 'phone', 'password', 'terms',
            ]);

        $this->assertGuest();
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function terms_must_be_accepted(): void
    {
        $this->post(route('register.store'), $this->validRegistrationPayload(['terms' => '0']))
            ->assertSessionHasErrors('terms');

        $this->assertDatabaseCount('organizations', 0);
    }

    #[Test]
    public function duplicate_email_is_rejected_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post(route('register.store'), $this->validRegistrationPayload(['email' => 'TAKEN@example.test']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('organizations', 1); // only the factory's organisation
    }

    #[Test]
    public function two_organisations_with_the_same_name_get_distinct_slugs(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->validRegistrationPayload(['email' => 'one@example.test']));
        auth()->logout();
        $this->post(route('register.store'), $this->validRegistrationPayload(['email' => 'two@example.test']));

        $slugs = Organization::query()->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique());
    }

    #[Test]
    public function registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), ['organization_name' => ''])->assertRedirect();
        }

        $this->post(route('register.store'), ['organization_name' => ''])->assertStatus(429);
    }

    #[Test]
    public function there_is_no_member_or_student_self_registration_route(): void
    {
        foreach (['/register/member', '/register/student', '/register/employee', '/signup', '/sign-up'] as $path) {
            $this->get($path)->assertNotFound();
            $this->post($path)->assertNotFound();
        }

        // The registration form always creates an organisation; a payload without organisation fields fails.
        $this->post(route('register.store'), [
            'first_name' => 'Solo',
            'last_name' => 'Member',
            'email' => 'solo@example.test',
            'phone' => '+233201234567',
            'password' => 'CorrectHorse42',
            'password_confirmation' => 'CorrectHorse42',
            'terms' => '1',
        ])->assertSessionHasErrors(['organization_name', 'organization_type', 'timezone']);

        $this->assertDatabaseCount('users', 0);
    }

    #[Test]
    public function authenticated_users_cannot_open_registration(): void
    {
        $this->actingAs($this->createOrganizationAdmin())
            ->get(route('register'))
            ->assertRedirect(route('app.dashboard'));
    }
}
