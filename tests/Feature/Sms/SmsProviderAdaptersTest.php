<?php

namespace Tests\Feature\Sms;

use App\Integrations\Sms\Providers\AfricasTalkingAdapter;
use App\Integrations\Sms\Providers\ArkeselAdapter;
use App\Integrations\Sms\Providers\TwilioAdapter;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmsProviderAdaptersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function twilio_sends_one_call_per_recipient_and_reads_account_balance(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('twilio')->create();
        $messages = [
            $this->message($provider, '+15550002222', 'One'),
            $this->message($provider, '+15550003333', 'Two'),
        ];

        Http::fake([
            'https://api.twilio.com/2010-04-01/Accounts/'.$provider->credential('account_sid').'/Messages.json' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ], 201),
            'https://api.twilio.com/2010-04-01/Accounts/'.$provider->credential('account_sid').'/Balance.json' => Http::response([
                'balance' => '15.50',
                'currency' => 'USD',
            ]),
        ]);

        $outcomes = (new TwilioAdapter)->deliver($provider, $messages);
        $balance = (new TwilioAdapter)->balance($provider);

        $this->assertCount(2, $outcomes);
        $this->assertSame('accepted', $outcomes[0]->result);
        $this->assertSame('SM123', $outcomes[0]->providerMessageId);
        $this->assertSame('15.50', $balance->amount);
        $this->assertSame('USD', $balance->unit);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/Messages.json')
            && $request['To'] === '+15550002222'
            && $request['From'] === '+15550001111'
            && str_contains($request['StatusCallback'], 'message='.$messages[0]->uuid));
    }

    #[Test]
    public function africas_talking_maps_gateway_acceptance_and_parses_balance(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('africastalking')->create(['sandbox' => true]);
        $message = $this->message($provider, '+254711000111', 'Hello');

        Http::fake([
            'https://api.sandbox.africastalking.com/version1/messaging/bulk' => Http::response([
                'SMSMessageData' => [
                    'Message' => 'Sent to 1/1',
                    'Recipients' => [[
                        'statusCode' => 102,
                        'number' => '+254711000111',
                        'status' => 'Success',
                        'cost' => 'KES 0.80',
                        'messageId' => 'ATXid_1',
                    ]],
                ],
            ]),
            'https://api.sandbox.africastalking.com/version1/user*' => Http::response([
                'UserData' => ['balance' => 'KES 1,785.50'],
            ]),
        ]);

        $outcome = (new AfricasTalkingAdapter)->deliver($provider, [$message])[0];
        $balance = (new AfricasTalkingAdapter)->balance($provider);

        $this->assertSame('accepted', $outcome->result);
        $this->assertSame('ATXid_1', $outcome->providerMessageId);
        $this->assertSame('1785.50', $balance->amount);
        $this->assertSame('KES', $balance->unit);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sandbox.africastalking.com/version1/messaging/bulk'
            && $request->hasHeader('apiKey', 'test-api-key')
            && $request['enqueue'] === 1
            && $request['phoneNumbers'] === ['+254711000111']);
    }

    #[Test]
    public function arkesel_sends_with_the_api_key_header_and_reads_sms_credits(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('arkesel')->create();
        $message = $this->message($provider, '+233244000000', 'Hello');

        Http::fake([
            'https://sms.arkesel.com/api/v2/sms/send' => Http::response([
                'status' => 'success',
                'data' => [[
                    'recipient' => '233244000000',
                    'id' => 'msg_123',
                ]],
            ]),
            'https://sms.arkesel.com/api/v2/clients/balance-details' => Http::response([
                'status' => 'success',
                'data' => [
                    'sms_balance' => '2003',
                    'main_balance' => 'GHS 20.99',
                ],
            ]),
        ]);

        $outcome = (new ArkeselAdapter)->deliver($provider, [$message])[0];
        $balance = (new ArkeselAdapter)->balance($provider);

        $this->assertSame('accepted', $outcome->result);
        $this->assertSame('msg_123', $outcome->providerMessageId);
        $this->assertSame('2003', $balance->amount);
        $this->assertSame('SMS', $balance->unit);
        $this->assertSame('20.99', $balance->cashAmount);
        $this->assertSame('GHS', $balance->cashUnit);

        Http::assertSent(fn ($request) => $request->url() === 'https://sms.arkesel.com/api/v2/sms/send'
            && $request->hasHeader('api-key', 'arkesel-api-key-value')
            && $request['sender'] === 'ORBIT'
            && $request['recipients'] === ['233244000000']
            && str_contains($request['callback_url'], 'message='.$message->uuid));
    }

    #[Test]
    public function a_later_africastalking_timeout_keeps_the_group_already_accepted(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('africastalking')->create();
        $first = $this->message($provider, '+254711000111', 'First text');
        $second = $this->message($provider, '+254711000222', 'Second text');
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 2) {
                throw new ConnectionException('timed out');
            }

            return Http::response([
                'SMSMessageData' => [
                    'Recipients' => [[
                        'statusCode' => 102,
                        'number' => '+254711000111',
                        'messageId' => 'ATXid_1',
                    ]],
                ],
            ]);
        });

        $outcomes = (new AfricasTalkingAdapter)->deliver($provider, [$first, $second]);

        $this->assertSame('accepted', $outcomes[0]->result);
        $this->assertSame('ATXid_1', $outcomes[0]->providerMessageId);
        $this->assertSame('retry', $outcomes[1]->result);
        $this->assertSame(2, $calls);
    }

    #[Test]
    public function a_later_arkesel_timeout_keeps_the_group_already_accepted(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('arkesel')->create();
        $first = $this->message($provider, '+233244000001', 'First text');
        $second = $this->message($provider, '+233244000002', 'Second text');
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 2) {
                throw new ConnectionException('timed out');
            }

            return Http::response([
                'status' => 'success',
                'data' => [[
                    'recipient' => '233244000001',
                    'id' => 'msg_1',
                ]],
            ]);
        });

        $outcomes = (new ArkeselAdapter)->deliver($provider, [$first, $second]);

        $this->assertSame('accepted', $outcomes[0]->result);
        $this->assertSame('msg_1', $outcomes[0]->providerMessageId);
        $this->assertSame('retry', $outcomes[1]->result);
        $this->assertSame(2, $calls);
    }

    #[Test]
    public function a_later_twilio_timeout_keeps_the_recipient_already_accepted(): void
    {
        Http::preventStrayRequests();
        $provider = SmsProvider::factory()->ready('twilio')->create();
        $first = $this->message($provider, '+15550002222', 'One');
        $second = $this->message($provider, '+15550003333', 'Two');
        $calls = 0;

        Http::fake(function () use (&$calls) {
            $calls++;

            if ($calls === 2) {
                throw new ConnectionException('timed out');
            }

            return Http::response(['sid' => 'SM123', 'status' => 'queued'], 201);
        });

        $outcomes = (new TwilioAdapter)->deliver($provider, [$first, $second]);

        $this->assertSame('accepted', $outcomes[0]->result);
        $this->assertSame('SM123', $outcomes[0]->providerMessageId);
        $this->assertSame('retry', $outcomes[1]->result);
        $this->assertSame(2, $calls);
    }

    private function message(SmsProvider $provider, string $to, string $body): SmsMessage
    {
        return SmsMessage::query()->create([
            'sms_provider_id' => $provider->id,
            'idempotency_key' => 'adapter:'.$to,
            'to' => $to,
            'body' => $body,
            'sender_id' => $provider->sender_id,
            'purpose' => 'platform_notification',
            'status' => 'queued',
            'max_attempts' => 3,
            'counts_toward_subscription' => false,
            'billing_scope' => 'platform',
            'queued_at' => now(),
        ]);
    }
}
