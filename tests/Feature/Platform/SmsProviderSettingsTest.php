<?php

namespace Tests\Feature\Platform;

use App\Integrations\Sms\SmsTransmitter;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_super_admin_configures_a_provider_without_the_secret_coming_back(): void
    {
        $admin = User::factory()->platform()->withRole('platform_super_admin')->create();
        $secret = 'wk_not_in_page_9kQ2';

        $this->actingAs($admin)->get(route('admin.sms-providers.index'))->assertRedirect();

        $wigal = SmsProvider::query()->where('key', 'wigal')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.sms-providers.update', $wigal), $this->payload($secret, true))
            ->assertRedirect(route('admin.sms-providers.show', $wigal));

        $page = $this->actingAs($admin)->get(route('admin.sms-providers.show', $wigal));
        $page->assertOk()
            ->assertSee('Wigal Frog')
            ->assertSee('Twilio')
            ->assertSee("Africa's Talking")
            ->assertSee('Arkesel')
            ->assertSee('••••9kQ2')
            ->assertDontSee($secret);

        $stored = (string) DB::table('sms_providers')->where('key', 'wigal')->value('credentials');
        $this->assertStringNotContainsString($secret, $stored);
        $this->assertTrue($wigal->fresh()->is_active);
    }

    #[Test]
    public function activating_another_provider_keeps_message_history(): void
    {
        $admin = User::factory()->platform()->withRole('platform_super_admin')->create();
        $this->actingAs($admin)->get(route('admin.sms-providers.index'));

        $wigal = SmsProvider::query()->where('key', 'wigal')->firstOrFail();
        $twilio = SmsProvider::query()->where('key', 'twilio')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.sms-providers.update', $wigal), $this->payload('secret-one-9kQ2', true));

        $message = SmsMessage::query()->create([
            'sms_provider_id' => $wigal->id,
            'idempotency_key' => 'history-1',
            'to' => '+233200000111',
            'body' => 'Earlier message',
            'purpose' => 'platform_notification',
            'status' => 'accepted',
            'counts_toward_subscription' => false,
            'billing_scope' => 'platform',
            'queued_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('admin.sms-providers.update', $twilio), [
            'sender_id' => '+15550001111',
            'enabled' => '1',
            'is_active' => '1',
            'is_fallback' => '0',
            'timeout_seconds' => 10,
            'max_attempts' => 3,
            'requests_per_minute' => 60,
            'backoff_seconds' => '30, 120, 300',
            'credentials' => [
                'account_sid' => 'ACaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'auth_token' => 'twilio-secret-token',
            ],
        ])->assertRedirect();

        $this->assertFalse($wigal->fresh()->is_active);
        $this->assertTrue($twilio->fresh()->is_active);
        $this->assertTrue($message->fresh()->exists());
        $this->assertSame($wigal->id, $message->fresh()->sms_provider_id);
    }

    #[Test]
    public function turning_the_active_provider_off_is_allowed_and_logs_later_messages(): void
    {
        Http::preventStrayRequests();
        $admin = User::factory()->platform()->withRole('platform_super_admin')->create();
        $this->actingAs($admin)->get(route('admin.sms-providers.index'));
        $wigal = SmsProvider::query()->where('key', 'wigal')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.sms-providers.update', $wigal), $this->payload('secret-one-9kQ2', true));
        $this->actingAs($admin)->put(route('admin.sms-providers.update', $wigal), $this->payload('secret-one-9kQ2', false, false));

        $this->assertFalse($wigal->fresh()->is_active);

        $message = app(SmsTransmitter::class)->transmit('+233200000111', 'Logged', 'after-off');

        $this->assertSame('logged', $message->fresh()->status->value);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_tenant_admin_and_platform_support_cannot_open_sms_settings(): void
    {
        $tenant = $this->createOrganizationAdmin();
        $support = User::factory()->platform()->withRole('platform_support')->create();

        $this->actingAs($tenant)->get(route('admin.sms-providers.index'))->assertForbidden();
        $this->actingAs($support)->get(route('admin.sms-providers.index'))->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $secret, bool $active, bool $enabled = true): array
    {
        return [
            'sender_id' => 'ORBIT',
            'enabled' => $enabled ? '1' : '0',
            'is_active' => $active ? '1' : '0',
            'is_fallback' => '0',
            'timeout_seconds' => 10,
            'max_attempts' => 3,
            'requests_per_minute' => 30,
            'backoff_seconds' => '30, 120, 300',
            'low_balance_threshold' => 25,
            'credentials' => [
                'username' => 'frog-user',
                'api_key' => $secret,
            ],
        ];
    }
}
