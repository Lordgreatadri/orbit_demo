<?php

namespace Tests\Feature\Sms;

use App\Auth\PhoneVerificationCode;
use App\Contracts\SmsSender;
use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\LogSmsSender;
use App\Integrations\Sms\SmsTransmitter;
use App\Jobs\Sms\RefreshSmsProviderBalance;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\User;
use App\Notifications\SmsBalanceLowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsTransmitterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_message_is_stored_before_dispatch_and_acceptance_is_not_delivery(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response([
                'status' => 'ACCEPTD',
                'message' => 'Message Accepted For Processing',
            ]),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response([
                'status' => 'SUCCESS',
                'message' => 'SUCCESS',
                'data' => [
                    'cashbalance' => 12.5,
                    'bundles' => ['SMS' => 5],
                ],
            ]),
        ]);

        $admin = User::factory()->platform()->create();
        $admin->assignRole('platform_super_admin');

        $provider = SmsProvider::factory()->ready()->create([
            'is_active' => true,
            'low_bundle_threshold' => 20,
            'low_balance_threshold' => null,
        ]);

        $message = app(SmsTransmitter::class)->transmit(
            '+233200000111',
            'Platform notice',
            'platform:notice:1',
        );

        $message->refresh();
        $provider->refresh();

        $this->assertSame(SmsMessageStatus::Accepted, $message->status);
        $this->assertNull($message->delivered_at);
        $this->assertFalse($message->counts_toward_subscription);
        $this->assertSame('platform', $message->billing_scope);
        $this->assertSame($provider->id, $message->sms_provider_id);
        $this->assertSame('5.00', $provider->balance);
        $this->assertSame('SMS', $provider->balance_unit);
        $this->assertSame(5, $provider->sms_bundle_balance);
        $this->assertSame('12.50', $provider->cash_balance);

        Http::assertSent(function ($request) use ($message) {
            return $request->url() === 'https://frogapi.wigal.com.gh/api/v3/sms/send'
                && $request['senderid'] === 'ORBIT'
                && $request['destinations'][0]['msgid'] === $message->uuid
                && $request['destinations'][0]['destination'] === '233200000111'
                && $request->hasHeader('API-KEY', 'test-api-key');
        });

        Notification::assertSentTo($admin, function (SmsBalanceLowNotification $notification): bool {
            return count($notification->reasons) === 1
                && $notification->reasons[0]['purse'] === 'bundle'
                && $notification->reasons[0]['balance'] === '5';
        });
    }

    #[Test]
    public function the_same_idempotency_key_does_not_send_twice(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'ACCEPTD', 'message' => 'Message Accepted For Processing']),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 80], 'cashbalance' => 1]]),
        ]);

        SmsProvider::factory()->ready()->create(['is_active' => true]);
        $transmitter = app(SmsTransmitter::class);

        $first = $transmitter->transmit('+233200000111', 'Hello', 'same-key');
        $second = $transmitter->transmit('+233200000111', 'Hello', 'same-key');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, SmsMessage::query()->count());
        Http::assertSentCount(2);
    }

    #[Test]
    public function no_active_provider_logs_the_message_and_does_not_call_a_provider(): void
    {
        Http::preventStrayRequests();
        Log::spy();

        $message = app(SmsTransmitter::class)->transmit('+233200000111', 'Kept in the log', 'logged-1');

        $this->assertSame(SmsMessageStatus::Logged, $message->fresh()->status);
        $this->assertNull($message->sms_provider_id);
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'no provider is active') && ($context['body'] ?? null) === 'Kept in the log';
        })->once();
    }

    #[Test]
    public function a_permanent_rejection_is_not_retried(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'UNAUTHORIZED', 'message' => 'no'], 401),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 1], 'cashbalance' => 0]]),
        ]);

        SmsProvider::factory()->ready()->create(['is_active' => true, 'max_attempts' => 3]);

        $message = app(SmsTransmitter::class)->transmit('+233200000111', 'No', 'reject-1');

        $this->assertSame(SmsMessageStatus::Failed, $message->fresh()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/sms/send'));
        $this->assertSame(1, collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), '/sms/send'))->count());
    }

    #[Test]
    public function a_timeout_style_failure_retries_until_the_attempt_limit(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::sequence()
                ->push(['status' => 'ERROR'], 503)
                ->push(['status' => 'ACCEPTD', 'message' => 'Message Accepted For Processing']),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 40], 'cashbalance' => 1]]),
        ]);

        SmsProvider::factory()->ready()->create([
            'is_active' => true,
            'max_attempts' => 2,
            'backoff_seconds' => [1, 1, 1],
        ]);

        $message = app(SmsTransmitter::class)->transmit('+233200000111', 'Retry me', 'retry-1');

        $this->assertSame(SmsMessageStatus::Accepted, $message->fresh()->status);
        $this->assertSame(1, $message->fresh()->attempts);
    }

    #[Test]
    public function bulk_messages_with_one_text_share_a_single_provider_call(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'ACCEPTD', 'message' => 'Message Accepted For Processing']),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 40], 'cashbalance' => 1]]),
        ]);

        SmsProvider::factory()->ready()->create(['is_active' => true]);

        $messages = app(SmsTransmitter::class)->transmitMany([
            ['to' => '+233200000111', 'body' => 'Same text', 'idempotency_key' => 'bulk-1'],
            ['to' => '+233200000222', 'body' => 'Same text', 'idempotency_key' => 'bulk-2'],
        ]);

        $this->assertCount(2, $messages);
        $this->assertTrue(collect($messages)->every(fn (SmsMessage $message) => $message->fresh()->status === SmsMessageStatus::Accepted));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sms/send')
                && count($request['destinations']) === 2
                && $request['message'] === 'Same text';
        });
    }

    #[Test]
    public function each_balance_warns_only_at_or_below_its_own_threshold(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $admin = User::factory()->platform()->create();
        $admin->assignRole('platform_super_admin');
        $provider = SmsProvider::factory()->ready()->create([
            'enabled' => true,
            'is_active' => true,
            'low_bundle_threshold' => 50,
            'low_balance_threshold' => null,
        ]);

        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response([
                'status' => 'SUCCESS',
                'data' => ['bundles' => ['SMS' => 174], 'cashbalance' => 0.01],
            ]),
        ]);

        RefreshSmsProviderBalance::dispatchSync($provider->id);

        Notification::assertNothingSent();
        $this->assertSame(174, $provider->fresh()->sms_bundle_balance);
        $this->assertSame('0.01', $provider->fresh()->cash_balance);

        $provider->forceFill(['low_balance_threshold' => 50])->save();
        RefreshSmsProviderBalance::dispatchSync($provider->id);

        Notification::assertSentTo($admin, function (SmsBalanceLowNotification $notification): bool {
            return count($notification->reasons) === 1
                && $notification->reasons[0]['purse'] === 'cash'
                && $notification->reasons[0]['balance'] === '0.01'
                && $notification->reasons[0]['threshold'] === '50.00';
        });
    }

    #[Test]
    public function an_sms_bundle_equal_to_its_threshold_warns(): void
    {
        Notification::fake();
        Http::preventStrayRequests();
        $admin = User::factory()->platform()->create();
        $admin->assignRole('platform_super_admin');
        $provider = SmsProvider::factory()->ready()->create([
            'enabled' => true,
            'is_active' => true,
            'low_bundle_threshold' => 50,
            'low_balance_threshold' => null,
        ]);

        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response([
                'status' => 'SUCCESS',
                'data' => ['bundles' => ['SMS' => 50], 'cashbalance' => 80],
            ]),
        ]);

        RefreshSmsProviderBalance::dispatchSync($provider->id);

        Notification::assertSentTo($admin, function (SmsBalanceLowNotification $notification): bool {
            return count($notification->reasons) === 1
                && $notification->reasons[0]['purse'] === 'bundle'
                && $notification->reasons[0]['balance'] === '50';
        });
    }

    #[Test]
    public function phone_confirmation_stays_on_the_log_channel_when_a_provider_is_active(): void
    {
        Http::preventStrayRequests();
        Log::spy();
        SmsProvider::factory()->ready()->create(['is_active' => true]);
        $user = User::factory()->create();

        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));

        app(PhoneVerificationCode::class)->issue($user);

        $this->assertSame(0, SmsMessage::query()->count());
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return str_contains($context['body'] ?? '', 'ORBIT verification code');
        })->once();
    }
}
