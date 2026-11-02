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
use App\Integrations\Sms\Webhooks\TwilioWebhookValidator;
use App\Jobs\Sms\ProcessTwilioSmsWebhook;
use App\Models\SmsProvider;

/**
 * Twilio Programmable Messaging. Reviewed 2026-11-02.
 *
 * Send: POST https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json
 * Auth: HTTP Basic (Account SID + Auth Token). There is no multi-recipient SMS method;
 * each recipient is one Messages API call, paced by the configured requests-per-minute guard.
 * HTTP status queued/sent means accepted. Delivered comes from the status callback.
 * Balance: GET .../Balance.json (account balance, not an SMS credit wallet).
 * Callbacks are form POSTs signed with X-Twilio-Signature (HMAC-SHA1). WhatsApp is not enabled.
 */
class TwilioAdapter implements SmsProviderAdapter
{
    public function key(): string
    {
        return 'twilio';
    }

    public function name(): string
    {
        return 'Twilio';
    }

    public function summary(): string
    {
        return 'Global messaging API. The sender is an E.164 number or a Messaging Service SID. Account balance is the prepaid balance, not an SMS bundle.';
    }

    public function documentationUrl(): string
    {
        return 'https://www.twilio.com/docs/messaging/quickstart';
    }

    public function reviewedOn(): string
    {
        return '2026-11-02';
    }

    public function defaultRequestsPerMinute(): int
    {
        return 60;
    }

    public function supportsSandbox(): bool
    {
        return false;
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
        return TwilioWebhookValidator::class;
    }

    public function processWebhookJob(): string
    {
        return ProcessTwilioSmsWebhook::class;
    }

    public function credentialFields(): array
    {
        return [
            ['key' => 'account_sid', 'label' => 'Account SID', 'secret' => false, 'hint' => 'Starts with AC. Used as the API username.'],
            ['key' => 'auth_token', 'label' => 'Auth token', 'secret' => true, 'hint' => 'HTTP Basic password and the key that signs status callbacks. Leave blank to keep the saved token.'],
        ];
    }

    public function deliver(SmsProvider $provider, array $messages): array
    {
        $sid = (string) $provider->credential('account_sid');
        $token = (string) $provider->credential('auth_token');
        $url = 'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Messages.json';
        $outcomes = [];

        foreach ($messages as $message) {
            $form = [
                'To' => $message->to,
                'Body' => $message->body,
                'StatusCallback' => $provider->webhookUrl().'?message='.$message->uuid,
            ];

            if (str_starts_with((string) $provider->sender_id, 'MG')) {
                $form['MessagingServiceSid'] = $provider->sender_id;
            } else {
                $form['From'] = $provider->sender_id;
            }

            $response = SmsHttp::send(
                SmsHttp::client($provider)->withBasicAuth($sid, $token)->asForm(),
                'post',
                $url,
                $form,
            );

            if ($failed = SmsOutcomes::fromTransport([$message], $response)) {
                $outcomes[] = $failed[0];

                continue;
            }

            $twilioStatus = strtolower((string) $response->json('status'));
            $messageSid = $response->json('sid');
            $messageSid = is_string($messageSid) ? $messageSid : null;

            if (in_array($twilioStatus, ['delivered', 'read'], true)) {
                $outcomes[] = new DeliveryOutcome($message->id, 'delivered', $messageSid, $twilioStatus);

                continue;
            }

            if (in_array($twilioStatus, ['failed', 'undelivered', 'canceled'], true)) {
                $code = $response->json('error_code');
                $outcomes[] = new DeliveryOutcome(
                    $message->id,
                    'failed',
                    $messageSid,
                    $twilioStatus,
                    'Twilio rejected the message'.(is_numeric($code) ? ' ('.$code.')' : '').'.',
                );

                continue;
            }

            $outcomes[] = new DeliveryOutcome($message->id, 'accepted', $messageSid, $twilioStatus !== '' ? $twilioStatus : 'queued');
        }

        return $outcomes;
    }

    public function balance(SmsProvider $provider): BalanceSnapshot
    {
        $sid = (string) $provider->credential('account_sid');
        $url = 'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Balance.json';
        $response = SmsHttp::send(
            SmsHttp::client($provider)->withBasicAuth($sid, (string) $provider->credential('auth_token')),
            'get',
            $url,
        );

        $transport = SmsHttp::transport($response);

        if ($transport !== null) {
            return new BalanceSnapshot(
                $transport === 'retry' ? SmsProviderHealth::Degraded : SmsProviderHealth::Down,
                SmsHttp::transportMessage($response),
            );
        }

        $amount = $response->json('balance');
        $currency = $response->json('currency');
        $unit = is_string($currency) && $currency !== '' ? strtoupper($currency) : 'USD';

        if (! is_numeric($amount)) {
            return new BalanceSnapshot(SmsProviderHealth::Degraded, 'The balance response had no amount.');
        }

        return new BalanceSnapshot(
            SmsProviderHealth::Healthy,
            'Account balance retrieved.',
            (string) $amount,
            $unit,
            (string) $amount,
            $unit,
        );
    }

    public function normalizeCallback(array $payload): ?NormalizedCallback
    {
        $sid = trim((string) ($payload['MessageSid'] ?? $payload['SmsSid'] ?? ''));
        $status = strtolower(trim((string) ($payload['MessageStatus'] ?? $payload['SmsStatus'] ?? '')));

        if ($sid === '' || $status === '') {
            return null;
        }

        $mapped = match ($status) {
            'delivered', 'read' => SmsMessageStatus::Delivered,
            'queued', 'accepted', 'sending', 'sent', 'receiving', 'received' => SmsMessageStatus::Accepted,
            'failed', 'undelivered', 'canceled' => SmsMessageStatus::Failed,
            default => null,
        };

        $uuid = trim((string) ($payload['message'] ?? ''));

        return new NormalizedCallback(
            eventKey: $sid.'|'.$status,
            status: $mapped,
            providerStatus: $status,
            correlationId: $sid,
            messageUuid: $uuid !== '' ? $uuid : null,
            reason: isset($payload['ErrorCode']) && $payload['ErrorCode'] !== '' ? 'Error '.$payload['ErrorCode'] : null,
        );
    }
}
