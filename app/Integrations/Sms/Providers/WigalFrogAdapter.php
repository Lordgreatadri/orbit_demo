<?php

namespace App\Integrations\Sms\Providers;

use App\Contracts\Sms\SmsProviderAdapter;
use App\Enums\SmsMessageStatus;
use App\Enums\SmsProviderHealth;
use App\Integrations\Sms\BalanceSnapshot;
use App\Integrations\Sms\DeliveryOutcome;
use App\Integrations\Sms\NormalizedCallback;
use App\Integrations\Sms\SmsHttp;
use App\Integrations\Sms\SmsOutcomes;
use App\Integrations\Sms\Webhooks\WigalWebhookValidator;
use App\Jobs\Sms\ProcessWigalFrogWebhook;
use App\Models\SmsMessage;
use App\Models\SmsProvider;

/**
 * Wigal FROG SMS. Reviewed 2026-11-02 against frogdocs.wigal.com.gh.
 *
 * Send: POST https://frogapi.wigal.com.gh/api/v3/sms/send
 * Auth: API-KEY and USERNAME headers. Approved sender IDs only.
 * ACCEPTD means the gateway queued the message. DELIVRD on the callback is delivery.
 * Balance: GET /api/v3/balance (SMS bundle and cash balance).
 * Callbacks are unsigned POSTs configured in the FROG dashboard. No numeric rate limit is published.
 */
class WigalFrogAdapter implements SmsProviderAdapter
{
    private const SEND = 'https://frogapi.wigal.com.gh/api/v3/sms/send';

    private const BALANCE = 'https://frogapi.wigal.com.gh/api/v3/balance';

    public function key(): string
    {
        return 'wigal';
    }

    public function name(): string
    {
        return 'Wigal Frog';
    }

    public function summary(): string
    {
        return 'Ghana SMS gateway. One request can carry a shared text or a personalised text per recipient. Acceptance is not delivery.';
    }

    public function documentationUrl(): string
    {
        return 'https://frogdocs.wigal.com.gh/send_general.html';
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
        return false;
    }

    public function tracksSmsBundle(): bool
    {
        return true;
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
        return WigalWebhookValidator::class;
    }

    public function processWebhookJob(): string
    {
        return ProcessWigalFrogWebhook::class;
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'username', 'label' => 'Username', 'secret' => false, 'hint' => 'FROG account username sent in the USERNAME header.'],
            ['key' => 'api_key', 'label' => 'API key', 'secret' => true, 'hint' => 'Sent in the API-KEY header. Leave blank to keep the saved key.'],
        ];
    }

    public function deliver(SmsProvider $provider, array $messages): array
    {
        $sameBody = count(array_unique(array_map(fn (SmsMessage $message) => $message->body, $messages))) === 1;
        $payload = $sameBody
            ? [
                'senderid' => $provider->sender_id,
                'message' => $messages[0]->body,
                'smstype' => 'text',
                'destinations' => array_map(fn (SmsMessage $message) => [
                    'destination' => ltrim($message->to, '+'),
                    'msgid' => $message->uuid,
                ], $messages),
            ]
            : [
                'senderid' => $provider->sender_id,
                'destinations' => array_map(fn (SmsMessage $message) => [
                    'destination' => ltrim($message->to, '+'),
                    'message' => $message->body,
                    'msgid' => $message->uuid,
                    'smstype' => 'text',
                ], $messages),
            ];

        $response = SmsHttp::send(
            SmsHttp::client($provider)->withHeaders([
                'API-KEY' => (string) $provider->credential('api_key'),
                'USERNAME' => (string) $provider->credential('username'),
            ]),
            'post',
            self::SEND,
            $payload,
        );

        if ($failed = SmsOutcomes::fromTransport($messages, $response)) {
            return $failed;
        }

        $status = strtoupper((string) $response->json('status'));

        if ($status === 'ACCEPTD') {
            return array_map(
                fn (SmsMessage $message) => new DeliveryOutcome($message->id, 'accepted', $message->uuid, 'ACCEPTD'),
                $messages,
            );
        }

        return SmsOutcomes::all($messages, 'failed', 'The provider did not accept the message.', $status !== '' ? $status : null);
    }

    public function balance(SmsProvider $provider): BalanceSnapshot
    {
        $response = SmsHttp::send(
            SmsHttp::client($provider)->withHeaders([
                'API-KEY' => (string) $provider->credential('api_key'),
                'USERNAME' => (string) $provider->credential('username'),
            ]),
            'get',
            self::BALANCE,
        );

        $transport = SmsHttp::transport($response);

        if ($transport !== null) {
            return new BalanceSnapshot(
                $transport === 'retry' ? SmsProviderHealth::Degraded : SmsProviderHealth::Down,
                SmsHttp::transportMessage($response),
            );
        }

        if (strtoupper((string) $response->json('status')) !== 'SUCCESS') {
            return new BalanceSnapshot(SmsProviderHealth::Degraded, 'The balance request was refused.');
        }

        $sms = $response->json('data.bundles.SMS');
        $cash = $response->json('data.cashbalance');

        return new BalanceSnapshot(
            SmsProviderHealth::Healthy,
            'Balance retrieved.',
            is_numeric($sms) ? (string) $sms : (is_numeric($cash) ? (string) $cash : null),
            is_numeric($sms) ? 'SMS' : (is_numeric($cash) ? 'cash' : null),
            is_numeric($cash) ? (string) $cash : null,
            is_numeric($cash) ? 'cash' : null,
            is_numeric($sms) ? (string) $sms : null,
            is_numeric($sms) ? 'SMS' : null,
        );
    }

    public function normalizeCallback(array $payload): ?NormalizedCallback
    {
        $msgid = trim((string) ($payload['msgid'] ?? ''));
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));

        if ($msgid === '' || $status === '') {
            return null;
        }

        $mapped = match ($status) {
            'DELIVRD' => SmsMessageStatus::Delivered,
            'ACCEPTD', 'WAITING' => SmsMessageStatus::Accepted,
            'UNDELIV', 'FAILED', 'REJECTD', 'EXPIRED', 'DELETED' => SmsMessageStatus::Failed,
            default => null,
        };

        $stamp = trim((string) ($payload['statusdate'] ?? ''));

        return new NormalizedCallback(
            eventKey: $msgid.'|'.$status.'|'.$stamp,
            status: $mapped,
            providerStatus: $status,
            correlationId: $msgid,
            messageUuid: $msgid,
            reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
        );
    }
}
