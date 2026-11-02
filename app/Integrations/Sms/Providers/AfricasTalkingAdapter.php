<?php

namespace App\Integrations\Sms\Providers;

use App\Contracts\Sms\SmsProviderAdapter;
use App\Enums\SmsMessageStatus;
use App\Enums\SmsProviderHealth;
use App\Integrations\Sms\BalanceSnapshot;
use App\Integrations\Sms\DeliveryOutcome;
use App\Integrations\Sms\E164;
use App\Integrations\Sms\NormalizedCallback;
use App\Integrations\Sms\SmsHttp;
use App\Integrations\Sms\SmsOutcomes;
use App\Integrations\Sms\Webhooks\AfricasTalkingWebhookValidator;
use App\Jobs\Sms\ProcessAfricasTalkingSmsWebhook;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use Illuminate\Http\Client\PendingRequest;

/**
 * Africa's Talking SMS. Reviewed 2026-11-02.
 *
 * Bulk send: POST https://api.africastalking.com/version1/messaging/bulk
 * Auth: apiKey header plus username in the JSON body. enqueue=1 asks AT to queue.
 * Sandbox host: https://api.sandbox.africastalking.com (the bulk path is documented as coming soon on sandbox).
 * statusCode 100/101/102 means accepted. Handset delivery is the later Success callback.
 * Balance: GET /version1/user?username=
 * Delivery reports are unsigned form POSTs. A secret path token is required because AT does not sign them.
 * WhatsApp and inbox fetch are separate products and are not wired here.
 */
class AfricasTalkingAdapter implements SmsProviderAdapter
{
    public function key(): string
    {
        return 'africastalking';
    }

    public function name(): string
    {
        return "Africa's Talking";
    }

    public function summary(): string
    {
        return 'Pan-African SMS. One bulk request shares a single text. Sandbox uses its own API key and the sandbox username.';
    }

    public function documentationUrl(): string
    {
        return 'https://developers.africastalking.com/docs/sms/sending/bulk';
    }

    public function reviewedOn(): string
    {
        return '2026-11-02';
    }

    public function defaultRequestsPerMinute(): int
    {
        return 30;
    }

    public function supportsSandbox(): bool
    {
        return true;
    }

    public function tracksSmsBundle(): bool
    {
        return false;
    }

    public function tracksCashBalance(): bool
    {
        return true;
    }

    public function webhookName(): string
    {
        return $this->key();
    }

    public function webhookMethods(): array
    {
        return ['post'];
    }

    public function signatureValidator(): string
    {
        return AfricasTalkingWebhookValidator::class;
    }

    public function processWebhookJob(): string
    {
        return ProcessAfricasTalkingSmsWebhook::class;
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'username', 'label' => 'Username', 'secret' => false, 'hint' => 'Application username. The sandbox username is sandbox.'],
            ['key' => 'api_key', 'label' => 'API key', 'secret' => true, 'hint' => 'Sent in the apiKey header. Leave blank to keep the saved key.'],
        ];
    }

    public function deliver(SmsProvider $provider, array $messages): array
    {
        $outcomes = [];

        foreach ($this->grouped($messages) as $body => $group) {
            $response = SmsHttp::send($this->request($provider), 'post', $this->base($provider).'/version1/messaging/bulk', [
                'username' => (string) $provider->credential('username'),
                'message' => $body,
                'senderId' => $provider->sender_id,
                'phoneNumbers' => array_map(fn (SmsMessage $message) => $message->to, $group),
                'enqueue' => 1,
            ]);

            if ($failed = SmsOutcomes::fromTransport($group, $response)) {
                array_push($outcomes, ...$failed);

                continue;
            }

            $recipients = $response->json('SMSMessageData.Recipients');
            $byNumber = [];

            foreach (is_array($recipients) ? $recipients : [] as $recipient) {
                if (is_array($recipient)) {
                    $byNumber[E164::digits((string) ($recipient['number'] ?? ''))] = $recipient;
                }
            }

            foreach ($group as $message) {
                $recipient = $byNumber[E164::digits($message->to)] ?? null;

                if (! is_array($recipient)) {
                    $outcomes[] = new DeliveryOutcome($message->id, 'failed', error: 'The provider omitted this recipient.');

                    continue;
                }

                $code = (int) ($recipient['statusCode'] ?? 0);
                $providerId = isset($recipient['messageId']) ? (string) $recipient['messageId'] : null;
                $providerStatus = (string) $code;

                if (in_array($code, [100, 101, 102], true)) {
                    $outcomes[] = new DeliveryOutcome($message->id, 'accepted', $providerId, $providerStatus);

                    continue;
                }

                $retry = in_array($code, [500, 501], true);
                $outcomes[] = new DeliveryOutcome(
                    $message->id,
                    $retry ? 'retry' : 'failed',
                    $providerId,
                    $providerStatus,
                    $retry ? 'The provider gateway failed.' : 'The provider rejected this recipient.',
                );
            }
        }

        return $outcomes;
    }

    public function balance(SmsProvider $provider): BalanceSnapshot
    {
        $username = (string) $provider->credential('username');
        $response = SmsHttp::send(
            $this->request($provider),
            'get',
            $this->base($provider).'/version1/user',
            ['username' => $username],
        );

        $transport = SmsHttp::transport($response);

        if ($transport !== null) {
            return new BalanceSnapshot(
                $transport === 'retry' ? SmsProviderHealth::Degraded : SmsProviderHealth::Down,
                SmsHttp::transportMessage($response),
            );
        }

        $raw = $response->json('UserData.balance');

        if (! is_string($raw) || ! preg_match('/^([A-Z]{3})\s+([\d,]+(?:\.\d+)?)$/', trim($raw), $matches)) {
            return new BalanceSnapshot(SmsProviderHealth::Degraded, 'The balance response was not readable.');
        }

        $amount = str_replace(',', '', $matches[2]);

        return new BalanceSnapshot(
            SmsProviderHealth::Healthy,
            'Balance retrieved.',
            $amount,
            $matches[1],
            $amount,
            $matches[1],
        );
    }

    public function normalizeCallback(array $payload): ?NormalizedCallback
    {
        $id = trim((string) ($payload['id'] ?? ''));
        $status = trim((string) ($payload['status'] ?? ''));

        if ($id === '' || $status === '') {
            return null;
        }

        $mapped = match (strtolower($status)) {
            'success' => SmsMessageStatus::Delivered,
            'sent', 'submitted', 'buffered' => SmsMessageStatus::Accepted,
            'failed', 'rejected', 'expired', 'absentsubscriber' => SmsMessageStatus::Failed,
            default => null,
        };

        return new NormalizedCallback(
            eventKey: $id.'|'.strtolower($status),
            status: $mapped,
            providerStatus: $status,
            correlationId: $id,
            reason: isset($payload['failureReason']) ? (string) $payload['failureReason'] : null,
        );
    }

    private function base(SmsProvider $provider): string
    {
        return $provider->sandbox
            ? 'https://api.sandbox.africastalking.com'
            : 'https://api.africastalking.com';
    }

    private function request(SmsProvider $provider): PendingRequest
    {
        return SmsHttp::client($provider)->withHeaders([
            'apiKey' => (string) $provider->credential('api_key'),
        ]);
    }

    /**
     * @param  list<SmsMessage>  $messages
     * @return array<string, list<SmsMessage>>
     */
    private function grouped(array $messages): array
    {
        $groups = [];

        foreach ($messages as $message) {
            $groups[$message->body][] = $message;
        }

        return $groups;
    }
}
