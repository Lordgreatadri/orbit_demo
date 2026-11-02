<?php

namespace Tests\Feature\Sms;

use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\Webhooks\SmsCallbackApplier;
use App\Integrations\Sms\Webhooks\SmsWebhookCall;
use App\Integrations\Sms\Webhooks\TwilioWebhookValidator;
use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use App\Models\SmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_wigal_callback_marks_delivery_and_a_repeat_is_ignored(): void
    {
        $provider = SmsProvider::factory()->ready()->create();
        $message = $this->accepted($provider, $provider->uuid);

        $payload = [
            'msgid' => $message->uuid,
            'status' => 'DELIVRD',
            'reason' => 'DELIVRD',
            'destination' => '233200000111',
            'statusdate' => '2026-11-02T06:00:00+00:00',
        ];

        $this->postJson(route('webhook-client-wigal', ['token' => $provider->credential('webhook_token')]), $payload)
            ->assertOk();

        $deliveredAt = $message->fresh()->delivered_at;
        $this->assertSame(SmsMessageStatus::Delivered, $message->fresh()->status);
        $this->assertSame(1, SmsMessageEvent::query()->where('type', 'callback')->count());

        $this->postJson(route('webhook-client-wigal', ['token' => $provider->credential('webhook_token')]), $payload)
            ->assertOk();

        $this->assertTrue($message->fresh()->delivered_at->eq($deliveredAt));
        $this->assertSame(1, SmsMessageEvent::query()->where('type', 'callback')->count());
        $this->assertStringContainsString('[redacted]', (string) SmsWebhookCall::query()->value('url'));
        $this->assertStringNotContainsString($provider->credential('webhook_token'), (string) SmsWebhookCall::query()->value('url'));
    }

    #[Test]
    public function a_callback_with_the_wrong_token_is_rejected_and_not_stored(): void
    {
        SmsProvider::factory()->ready()->create();

        $this->postJson(route('webhook-client-wigal', ['token' => str_repeat('a', 48)]), [
            'msgid' => 'missing',
            'status' => 'DELIVRD',
        ])->assertUnauthorized();

        $this->assertSame(0, SmsWebhookCall::query()->count());
    }

    #[Test]
    public function an_africas_talking_success_callback_means_delivered(): void
    {
        $provider = SmsProvider::factory()->ready('africastalking')->create();
        $message = $this->accepted($provider, 'ATXid_9');

        $this->post(route('webhook-client-africastalking', ['token' => $provider->credential('webhook_token')]), [
            'id' => 'ATXid_9',
            'status' => 'Success',
            'phoneNumber' => '+254711000111',
            'networkCode' => '63902',
        ])->assertOk();

        $this->assertSame(SmsMessageStatus::Delivered, $message->fresh()->status);
    }

    #[Test]
    public function an_arkesel_query_callback_marks_delivered(): void
    {
        $provider = SmsProvider::factory()->ready('arkesel')->create();
        $message = $this->accepted($provider, 'msg_55');

        $this->get(route('webhook-client-arkesel', ['token' => $provider->credential('webhook_token')]).'?sms_id=msg_55&status=DELIVERED')
            ->assertOk();

        $this->assertSame(SmsMessageStatus::Delivered, $message->fresh()->status);
    }

    #[Test]
    public function a_twilio_callback_must_carry_a_valid_signature(): void
    {
        $provider = SmsProvider::factory()->ready('twilio')->create();
        $message = $this->accepted($provider, 'SM999');
        $url = route('webhook-client-twilio', ['token' => $provider->credential('webhook_token')]).'?message='.$message->uuid;
        $params = [
            'MessageSid' => 'SM999',
            'MessageStatus' => 'delivered',
            'To' => '+15550002222',
            'From' => '+15550001111',
        ];

        $this->post($url, $params)->assertUnauthorized();

        $signature = TwilioWebhookValidator::signature($url, $params, 'twilio-auth-token-value');

        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertOk();

        $this->assertSame(SmsMessageStatus::Delivered, $message->fresh()->status);
    }

    #[Test]
    public function a_callback_cannot_update_another_providers_message(): void
    {
        $twilio = SmsProvider::factory()->ready('twilio')->create();
        $arkesel = SmsProvider::factory()->ready('arkesel')->create();
        $twilioMessage = $this->accepted($twilio, 'shared-id', 'twilio-shared');
        $arkeselMessage = $this->accepted($arkesel, 'shared-id', 'arkesel-shared');

        $this->get(route('webhook-client-arkesel', ['token' => $arkesel->credential('webhook_token')]).'?sms_id=shared-id&status=DELIVERED')
            ->assertOk();

        $this->assertSame(SmsMessageStatus::Delivered, $arkeselMessage->fresh()->status);
        $this->assertSame(SmsMessageStatus::Accepted, $twilioMessage->fresh()->status);

        $url = route('webhook-client-twilio', ['token' => $twilio->credential('webhook_token')]).'?message='.$arkeselMessage->uuid;
        $params = [
            'MessageSid' => 'SM-other',
            'MessageStatus' => 'delivered',
        ];
        $signature = TwilioWebhookValidator::signature($url, $params, 'twilio-auth-token-value');

        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertOk();

        $this->assertSame(SmsMessageStatus::Delivered, $arkeselMessage->fresh()->status);
        $this->assertSame(SmsMessageStatus::Accepted, $twilioMessage->fresh()->status);
    }

    #[Test]
    public function a_non_uuid_provider_id_is_not_compared_with_the_uuid_column(): void
    {
        $provider = SmsProvider::factory()->ready('africastalking')->create();
        $message = $this->accepted($provider, 'ATXid_9');
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
        });

        app(SmsCallbackApplier::class)->apply('africastalking', [
            'id' => 'ATXid_9',
            'status' => 'Success',
        ]);

        $this->assertSame(SmsMessageStatus::Delivered, $message->fresh()->status);

        foreach ($queries as $query) {
            if (in_array('ATXid_9', $query['bindings'], true)) {
                $this->assertStringNotContainsString('uuid', strtolower($query['sql']));
            }
        }
    }

    private function accepted(SmsProvider $provider, string $providerMessageId, ?string $idempotencyKey = null): SmsMessage
    {
        return SmsMessage::query()->create([
            'sms_provider_id' => $provider->id,
            'idempotency_key' => $idempotencyKey ?? ('webhook:'.$providerMessageId),
            'to' => '+233200000111',
            'body' => 'Hello',
            'sender_id' => $provider->sender_id,
            'purpose' => 'platform_notification',
            'status' => SmsMessageStatus::Accepted,
            'provider_message_id' => $providerMessageId,
            'accepted_at' => now(),
            'max_attempts' => 3,
            'counts_toward_subscription' => false,
            'billing_scope' => 'platform',
            'queued_at' => now(),
        ]);
    }
}
