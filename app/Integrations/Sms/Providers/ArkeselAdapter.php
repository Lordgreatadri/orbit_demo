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
use App\Integrations\Sms\Webhooks\ArkeselWebhookValidator;
use App\Jobs\Sms\ProcessArkeselSmsWebhook;
use App\Models\SmsMessage;
use App\Models\SmsProvider;

/**
 * Arkesel SMS v2. Reviewed 2026-11-02.
 *
 * Send: POST https://sms.arkesel.com/api/v2/sms/send with the api-key header.
 * One request accepts many recipients for the same text. callback_url is set per request.
 * A success response means accepted. Delivery is a later callback (often batched) with
 * query parameters sms_id and status. DELIVERED is the handset state.
 * Balance: GET /api/v2/clients/balance-details (sms_balance and main_balance).
 * The published callback has no HMAC. A secret path token authenticates it.
 * Voice, OTP, and WhatsApp-style products are not wired here.
 */
class ArkeselAdapter implements SmsProviderAdapter
{
    private const SEND = 'https://sms.arkesel.com/api/v2/sms/send';

    private const BALANCE = 'https://sms.arkesel.com/api/v2/clients/balance-details';

    public function key(): string
    {
        return 'arkesel';
    }

    public function name(): string
    {
        return 'Arkesel';
    }

    public function summary(): string
    {
        return 'Ghana and Nigeria SMS. SMS credits and the main cash balance are reported separately. Sandbox uses a sandbox API key on the same host.';
    }

    public function documentationUrl(): string
    {
        return 'https://developers.arkesel.com/#/sms-v2/send_sms';
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
        return ['get', 'post'];
    }

    public function signatureValidator(): string
    {
        return ArkeselWebhookValidator::class;
    }

    public function processWebhookJob(): string
    {
        return ProcessArkeselSmsWebhook::class;
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'API key', 'secret' => true, 'hint' => 'Sent in the api-key header. A sandbox key uses the same host. Leave blank to keep the saved key.'],
        ];
    }

    public function deliver(SmsProvider $provider, array $messages): array
    {
        $outcomes = [];

        foreach ($this->grouped($messages) as $body => $group) {
            $callback = $provider->webhookUrl();

            if (count($group) === 1) {
                $callback .= '?message='.$group[0]->uuid;
            }

            $response = SmsHttp::send(
                SmsHttp::client($provider)->withHeaders([
                    'api-key' => (string) $provider->credential('api_key'),
                ]),
                'post',
                self::SEND,
                [
                    'sender' => $provider->sender_id,
                    'message' => $body,
                    'recipients' => array_map(fn (SmsMessage $message) => ltrim($message->to, '+'), $group),
                    'callback_url' => $callback,
                ],
            );

            if ($failed = SmsOutcomes::fromTransport($group, $response)) {
                array_push($outcomes, ...$failed);

                continue;
            }

            if (strtolower((string) $response->json('status')) !== 'success') {
                array_push($outcomes, ...SmsOutcomes::all($group, 'failed', 'The provider rejected the message.', 'error'));

                continue;
            }

            $rows = $this->rows($response->json('data'));
            $byNumber = [];

            foreach ($rows as $row) {
                $number = $row['recipient'] ?? $row['phone'] ?? null;
                if (is_string($number)) {
                    $byNumber[E164::digits($number)] = $row;
                }
            }

            foreach ($group as $index => $message) {
                $row = $byNumber[E164::digits($message->to)] ?? (count($group) === 1 ? ($rows[0] ?? null) : ($rows[$index] ?? null));
                $id = is_array($row) ? ($row['id'] ?? $row['sms_id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    $outcomes[] = new DeliveryOutcome($message->id, 'failed', error: 'The provider did not return a message id.');

                    continue;
                }

                $outcomes[] = new DeliveryOutcome($message->id, 'accepted', $id, 'success');
            }
        }

        return $outcomes;
    }

    public function balance(SmsProvider $provider): BalanceSnapshot
    {
        $response = SmsHttp::send(
            SmsHttp::client($provider)->withHeaders([
                'api-key' => (string) $provider->credential('api_key'),
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

        $sms = $response->json('data.sms_balance');
        $main = $response->json('data.main_balance');
        $cash = null;
        $cashUnit = null;

        if (is_string($main) && preg_match('/^([A-Z]{3})\s+([\d,]+(?:\.\d+)?)$/', trim($main), $matches)) {
            $cashUnit = $matches[1];
            $cash = str_replace(',', '', $matches[2]);
        } elseif (is_numeric($main)) {
            $cash = (string) $main;
        }

        if (! is_numeric($sms) && $cash === null) {
            return new BalanceSnapshot(SmsProviderHealth::Degraded, 'The balance response was not readable.');
        }

        return new BalanceSnapshot(
            SmsProviderHealth::Healthy,
            'Balance retrieved.',
            is_numeric($sms) ? (string) $sms : $cash,
            is_numeric($sms) ? 'SMS' : $cashUnit,
            $cash,
            $cashUnit,
            is_numeric($sms) ? (string) $sms : null,
            is_numeric($sms) ? 'SMS' : null,
        );
    }

    public function normalizeCallback(array $payload): ?NormalizedCallback
    {
        $id = trim((string) ($payload['sms_id'] ?? $payload['id'] ?? ''));
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));

        if ($id === '' || $status === '') {
            return null;
        }

        $mapped = match ($status) {
            'DELIVERED' => SmsMessageStatus::Delivered,
            'SUBMITTED', 'QUEUED' => SmsMessageStatus::Accepted,
            'NOT_DELIVERED', 'PROHIBITED', 'EXPIRED' => SmsMessageStatus::Failed,
            default => null,
        };

        $uuid = trim((string) ($payload['message'] ?? ''));

        return new NormalizedCallback(
            eventKey: $id.'|'.$status,
            status: $mapped,
            providerStatus: $status,
            correlationId: $id,
            messageUuid: $uuid !== '' ? $uuid : null,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['recipients']) && is_array($data['recipients'])) {
            return array_values(array_filter($data['recipients'], 'is_array'));
        }

        if (isset($data['id']) || isset($data['sms_id'])) {
            return [$data];
        }

        return array_values(array_filter($data, 'is_array'));
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
