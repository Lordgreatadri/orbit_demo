<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsSender;
use App\Integrations\Sms\LogSmsSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function email_and_phone_checks_are_shown_together_and_block_the_workspace(): void
    {
        $user = User::factory()->unverified()->unverifiedPhone()->create();

        $this->actingAs($user)->get(route('app.dashboard'))->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verify your e-mail address')
            ->assertSee('Verify your phone')
            ->assertSee('application log');
    }

    #[Test]
    public function a_code_from_the_sms_sender_confirms_the_phone_and_opens_the_workspace(): void
    {
        $sender = new class implements SmsSender
        {
            public string $message = '';

            public function send(string $to, string $message): void
            {
                $this->message = $message;
            }
        };
        $this->app->instance(SmsSender::class, $sender);

        $user = User::factory()->unverifiedPhone()->create(['phone' => '+233302000111']);

        $this->actingAs($user)->post(route('verification.phone.send'))->assertRedirect();

        preg_match('/(\d{6})/', $sender->message, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $this->actingAs($user)
            ->post(route('verification.phone.confirm'), ['code' => $matches[1]])
            ->assertRedirect(route('app.dashboard', absolute: false));

        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->actingAs($user->fresh())->get(route('app.dashboard'))->assertRedirect(route('org.dashboard'));
    }

    #[Test]
    public function confirming_the_phone_still_keeps_an_unverified_email_on_the_same_page(): void
    {
        $sender = new class implements SmsSender
        {
            public string $message = '';

            public function send(string $to, string $message): void
            {
                $this->message = $message;
            }
        };
        $this->app->instance(SmsSender::class, $sender);

        $user = User::factory()->unverified()->unverifiedPhone()->create(['phone' => '+233302000222']);

        $this->actingAs($user)->post(route('verification.phone.send'));
        preg_match('/(\d{6})/', $sender->message, $matches);

        $this->actingAs($user)
            ->post(route('verification.phone.confirm'), ['code' => $matches[1]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($user->fresh())->get(route('app.dashboard'))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function an_incorrect_code_does_not_verify_the_phone(): void
    {
        $user = User::factory()->unverifiedPhone()->create(['phone' => '+233302000333']);

        $this->actingAs($user)->post(route('verification.phone.send'));
        $this->actingAs($user)
            ->post(route('verification.phone.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    #[Test]
    public function the_log_sender_records_the_code_for_the_technical_team(): void
    {
        Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'not configured')
                && ($context['to'] ?? null) === '+233200000000'
                && str_contains((string) ($context['body'] ?? ''), '123456');
        });

        (new LogSmsSender)->send('+233200000000', 'ORBIT verification code: 123456. It expires in 10 minutes.');

        $this->assertInstanceOf(LogSmsSender::class, $this->app->make(SmsSender::class));
    }
}
