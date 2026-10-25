<?php

namespace Tests\Feature\Public;

use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kofi Example',
            'email' => 'Kofi@Example.test',
            'organization_name' => 'Fictional Agency',
            'subject' => 'Demo request',
            'message' => 'We would like to see a demonstration of ORBIT for our regional offices.',
        ], $overrides);
    }

    #[Test]
    public function a_visitor_can_send_a_contact_message(): void
    {
        Notification::fake();
        config(['orbit.contact_recipient' => 'inbox@orbit.test']);

        $this->from(route('contact'))
            ->post(route('contact.store'), $this->payload())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'kofi@example.test',
            'subject' => 'Demo request',
            'status' => 'new',
        ]);

        $message = ContactMessage::query()->sole();
        $this->assertNotNull($message->uuid);

        Notification::assertSentOnDemand(
            ContactMessageReceived::class,
            fn (ContactMessageReceived $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'inbox@orbit.test'
                && $n->contactMessage->is($message)
        );
    }

    #[Test]
    public function message_is_stored_without_notification_when_no_recipient_configured(): void
    {
        Notification::fake();
        config(['orbit.contact_recipient' => null]);

        $this->post(route('contact.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseCount('contact_messages', 1);
        Notification::assertNothingSent();
    }

    #[Test]
    public function validation_errors_are_returned_and_nothing_is_stored(): void
    {
        $this->from(route('contact'))
            ->post(route('contact.store'), $this->payload([
                'name' => '',
                'email' => 'not-an-email',
                'subject' => '',
                'message' => 'too short',
            ]))
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    #[Test]
    public function honeypot_submissions_are_silently_discarded(): void
    {
        Notification::fake();
        config(['orbit.contact_recipient' => 'inbox@orbit.test']);

        $this->post(route('contact.store'), $this->payload(['website' => 'https://spam.example']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('contact_messages', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function contact_form_is_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), $this->payload())->assertRedirect();
        }

        $this->post(route('contact.store'), $this->payload())->assertStatus(429);
    }
}
