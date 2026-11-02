<?php

namespace Tests\Feature\Sms;

use App\Auth\PhoneVerificationCode;
use App\Contracts\SmsSender;
use App\Enums\SmsMessageStatus;
use App\Integrations\Sms\Exceptions\InvalidPhoneNumber;
use App\Integrations\Sms\LogSmsSender;
use App\Integrations\Sms\SmsTransmitter;
use App\Jobs\Sms\DeliverSmsMessages;
use App\Jobs\Sms\RefreshSmsProviderBalance;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\User;
use App\Notifications\SmsBalanceLowNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
    public function an_invalid_later_message_does_not_leave_the_earlier_one_queued(): void
    {
        Http::preventStrayRequests();
        SmsProvider::factory()->ready()->create(['is_active' => true]);

        try {
            app(SmsTransmitter::class)->transmitMany([
                ['to' => '+233200000111', 'body' => 'Ok', 'idempotency_key' => 'batch-ok'],
                ['to' => 'not-a-phone', 'body' => 'Bad', 'idempotency_key' => 'batch-bad'],
            ]);
            $this->fail('The invalid number should have been rejected.');
        } catch (InvalidPhoneNumber) {
            $this->assertSame(0, SmsMessage::query()->count());
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function a_message_already_being_sent_is_not_sent_again(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'ACCEPTD', 'message' => 'ok']),
        ]);
        $provider = SmsProvider::factory()->ready()->create();
        $message = SmsMessage::query()->create([
            'sms_provider_id' => $provider->id,
            'idempotency_key' => 'claim-1',
            'to' => '+233200000111',
            'body' => 'Once',
            'purpose' => 'platform_notification',
            'status' => SmsMessageStatus::Queued,
            'max_attempts' => 3,
            'counts_toward_subscription' => false,
            'billing_scope' => 'platform',
            'queued_at' => now(),
        ]);
        $lock = Cache::lock(DeliverSmsMessages::claimKey($message->id), 90);
        $this->assertTrue($lock->get());

        DeliverSmsMessages::dispatch([$message->id]);

        Http::assertNothingSent();
        $this->assertSame(SmsMessageStatus::Queued, $message->fresh()->status);
        $lock->release();
    }

    #[Test]
    public function a_delivery_report_during_the_send_is_not_replaced_by_acceptance(): void
    {
        Http::preventStrayRequests();
        SmsProvider::factory()->ready()->create(['is_active' => true]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/balance')) {
                return Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 10], 'cashbalance' => 1]]);
            }

            SmsMessage::query()->first()?->forceFill([
                'status' => SmsMessageStatus::Delivered,
                'delivered_at' => now(),
                'provider_status' => 'DELIVRD',
            ])->save();

            return Http::response(['status' => 'ACCEPTD', 'message' => 'ok']);
        });

        $message = app(SmsTransmitter::class)->transmit('+233200000111', 'Hello', 'race-1');

        $this->assertSame(SmsMessageStatus::Delivered, $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
    }

    #[Test]
    public function a_wigal_batch_above_the_recipient_cap_still_sends_once(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'ACCEPTD', 'message' => 'ok']),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 40], 'cashbalance' => 1]]),
        ]);
        SmsProvider::factory()->ready()->create([
            'is_active' => true,
            'requests_per_minute' => 30,
        ]);

        $items = [];
        for ($index = 1; $index <= 31; $index++) {
            $items[] = [
                'to' => '+23324'.str_pad((string) $index, 8, '0', STR_PAD_LEFT),
                'body' => 'Same text',
                'idempotency_key' => 'cap-'.$index,
            ];
        }

        $messages = app(SmsTransmitter::class)->transmitMany($items);

        $this->assertCount(31, $messages);
        $this->assertTrue(collect($messages)->every(
            fn (SmsMessage $message) => $message->fresh()->status === SmsMessageStatus::Accepted,
        ));
        $this->assertSame(1, collect(Http::recorded())->filter(
            fn ($pair) => str_contains($pair[0]->url(), '/sms/send'),
        )->count());
    }

    #[Test]
    public function the_sync_queue_fails_recipients_it_cannot_reschedule(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('twilio')->create([
            'is_active' => true,
            'requests_per_minute' => 1,
        ]);
        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/'.$provider->credential('account_sid').'/Messages.json' => Http::response(['sid' => 'SM1', 'status' => 'queued'], 201),
            'https://api.twilio.com/2010-04-01/Accounts/'.$provider->credential('account_sid').'/Balance.json' => Http::response(['balance' => '1', 'currency' => 'USD']),
        ]);

        $messages = app(SmsTransmitter::class)->transmitMany([
            ['to' => '+15550002222', 'body' => 'One', 'idempotency_key' => 'sync-1'],
            ['to' => '+15550003333', 'body' => 'Two', 'idempotency_key' => 'sync-2'],
        ]);

        $this->assertSame(SmsMessageStatus::Accepted, $messages[0]->fresh()->status);
        $this->assertSame(SmsMessageStatus::Failed, $messages[1]->fresh()->status);
        $this->assertStringContainsString('rate limit', (string) $messages[1]->fresh()->last_error);
        $this->assertSame(1, collect(Http::recorded())->filter(
            fn ($pair) => str_contains($pair[0]->url(), '/Messages.json'),
        )->count());
    }

    #[Test]
    public function fallback_delivery_obeys_the_fallback_providers_rate_limit(): void
    {
        Http::preventStrayRequests();
        SmsProvider::factory()->ready()->create([
            'is_active' => true,
            'max_attempts' => 1,
        ]);
        SmsProvider::factory()->ready('arkesel')->create([
            'is_fallback' => true,
            'requests_per_minute' => 1,
        ]);
        RateLimiter::increment('sms-provider:arkesel', 60, 1);
        Http::fake([
            'https://frogapi.wigal.com.gh/api/v3/sms/send' => Http::response(['status' => 'UNAUTHORIZED'], 401),
            'https://frogapi.wigal.com.gh/api/v3/balance' => Http::response(['status' => 'SUCCESS', 'data' => ['bundles' => ['SMS' => 40], 'cashbalance' => 1]]),
            'https://sms.arkesel.com/api/v2/sms/send' => Http::response(['status' => 'success', 'data' => [['id' => 'msg_1', 'recipient' => '233200000111']]]),
        ]);

        $message = app(SmsTransmitter::class)->transmit('+233200000111', 'Fallback', 'fallback-limit');

        $this->assertSame(SmsMessageStatus::Failed, $message->fresh()->status);
        $this->assertStringContainsString('rate limit', (string) $message->fresh()->last_error);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'arkesel.com'));
    }

    #[Test]
    public function a_low_balance_warning_is_stored_as_a_database_notification(): void
    {
        Mail::fake();
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

        $stored = DB::table('notifications')->first();
        $this->assertNotNull($stored);
        $this->assertTrue(is_numeric($stored->id));
        $this->assertTrue(Str::isUuid($stored->uuid));
        $this->assertSame(SmsBalanceLowNotification::class, $stored->type);
        $notification = $admin->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('bundle', $notification->data['reasons'][0]['purse']);
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
