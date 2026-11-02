<?php

namespace Tests\Feature\Platform;

use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use App\Models\SmsProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsOverviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_super_admin_sees_delivery_separate_from_acceptance(): void
    {
        $admin = User::factory()->platform()->withRole('platform_super_admin')->create();
        $provider = SmsProvider::factory()->ready()->create([
            'enabled' => true,
            'is_active' => true,
            'balance' => '174.00',
            'balance_unit' => 'SMS',
            'cash_balance' => '0.01',
            'cash_balance_unit' => 'cash',
        ]);

        foreach (['delivered', 'accepted', 'failed'] as $status) {
            $this->message($provider, $status, 'secret-body-'.$status);
        }
        $this->message(null, 'logged', 'kept-in-the-log-only');

        $page = $this->actingAs($admin)->get(route('admin.sms.overview'));

        $page->assertOk()
            ->assertSee('33% confirmed delivered')
            ->assertSee('Wigal Frog')
            ->assertSee('174 SMS')
            ->assertSee('0.01 cash')
            ->assertDontSee('secret-body-delivered')
            ->assertDontSee('+233200000111')
            ->assertSee('••••0111');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('33% confirmed delivered')
            ->assertSee('SMS overview');
    }

    #[Test]
    public function a_super_admin_can_open_one_message_and_its_status(): void
    {
        $admin = User::factory()->platform()->withRole('platform_super_admin')->create();
        $provider = SmsProvider::factory()->ready()->create();
        $message = SmsMessage::query()->create([
            'sms_provider_id' => $provider->id,
            'idempotency_key' => 'detail:1',
            'to' => '+233200000111',
            'body' => 'Gate opens at 07:00.',
            'sender_id' => 'ORBIT',
            'purpose' => 'platform_notification',
            'status' => 'accepted',
            'provider_status' => 'ACCEPTD',
            'attempts' => 1,
            'max_attempts' => 3,
            'counts_toward_subscription' => false,
            'billing_scope' => 'platform',
            'queued_at' => now()->subMinute(),
            'accepted_at' => now(),
        ]);
        SmsMessageEvent::query()->create([
            'sms_message_id' => $message->id,
            'sms_provider_id' => $provider->id,
            'provider_key' => 'wigal',
            'type' => 'callback',
            'status' => 'accepted',
            'provider_status' => 'ACCEPTD',
            'event_key' => 'detail:callback',
            'payload' => ['reason' => 'Waiting for the handset'],
        ]);

        $this->actingAs($admin)->get(route('admin.sms.overview'))
            ->assertOk()
            ->assertDontSee('Gate opens at 07:00.')
            ->assertSee(route('admin.sms.messages.show', $message), false);

        $this->actingAs($admin)->get(route('admin.sms.messages.show', $message))
            ->assertOk()
            ->assertSee('Gate opens at 07:00.')
            ->assertSee('+233200000111')
            ->assertSee('Accepted')
            ->assertSee('ACCEPTD')
            ->assertSee('Delivery callback')
            ->assertSee('Waiting for the handset')
            ->assertSee('Paid by ORBIT');

        $support = User::factory()->platform()->withRole('platform_support')->create();
        $this->actingAs($support)->get(route('admin.sms.messages.show', $message))->assertForbidden();
        $this->actingAs($this->createOrganizationAdmin())->get(route('admin.sms.messages.show', $message))->assertForbidden();
    }

    #[Test]
    public function message_tables_page_past_the_first_fifteen_rows(): void
    {
        $admin = User::factory()->platform()->withRole('platform_super_admin')->create();
        $provider = SmsProvider::factory()->ready()->create();

        for ($index = 1; $index <= 16; $index++) {
            SmsMessage::query()->create([
                'sms_provider_id' => $provider->id,
                'idempotency_key' => 'page:'.$index,
                'to' => '+233240000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'body' => 'hidden-body-'.$index,
                'purpose' => $index === 1 ? 'oldestmarker' : ($index === 16 ? 'newestmarker' : 'filler'.$index),
                'status' => 'accepted',
                'counts_toward_subscription' => false,
                'billing_scope' => 'platform',
                'queued_at' => now(),
            ]);
        }

        $first = $this->actingAs($admin)->get(route('admin.sms.overview'));
        $first->assertOk()->assertSee('Showing')->assertSee('newestmarker')->assertDontSee('oldestmarker')->assertDontSee('hidden-body-16');

        $this->actingAs($admin)->get(route('admin.sms.overview', ['messages' => 2]))
            ->assertOk()
            ->assertSee('oldestmarker')
            ->assertDontSee('newestmarker');

        $this->actingAs($admin)->get(route('admin.sms-providers.show', $provider))
            ->assertOk()
            ->assertSee('newestmarker')
            ->assertDontSee('oldestmarker');

        $this->actingAs($admin)->get(route('admin.sms-providers.show', ['smsProvider' => $provider, 'messages' => 2]))
            ->assertOk()
            ->assertSee('oldestmarker');
    }

    #[Test]
    public function support_and_tenant_admins_cannot_open_the_sms_overview(): void
    {
        $support = User::factory()->platform()->withRole('platform_support')->create();
        $tenant = $this->createOrganizationAdmin();

        $this->actingAs($support)->get(route('admin.sms.overview'))->assertForbidden();
        $this->actingAs($tenant)->get(route('admin.sms.overview'))->assertForbidden();
    }

    private function message(?SmsProvider $provider, string $status, string $body): void
    {
        SmsMessage::query()->create([
            'sms_provider_id' => $provider?->id,
            'idempotency_key' => 'overview:'.$status,
            'to' => '+233200000111',
            'body' => $body,
            'purpose' => 'platform_notification',
            'status' => $status,
            'counts_toward_subscription' => false,
            'billing_scope' => 'platform',
            'queued_at' => now(),
        ]);
    }
}
