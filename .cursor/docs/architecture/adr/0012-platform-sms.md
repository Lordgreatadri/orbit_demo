# ADR-0012: Platform SMS providers

- Status: Accepted
- Date: 2026-11-02

## Context
ORBIT needs one place to send platform SMS, with more than one gateway available and the ability to add or remove a gateway later. The platform pays for these messages. Organisation subscriptions, when they exist, must not be charged for them. Phone confirmation already has its own sender and must keep using the log channel.

The provider contracts were reviewed on 2026-11-02:

- Wigal FROG: `POST /api/v3/sms/send`, `GET /api/v3/balance`, `API-KEY` and `USERNAME` headers. `ACCEPTD` means accepted for processing. Delivery is an unsigned dashboard callback; `DELIVRD` means delivered. No numeric rate limit is published.
- Twilio Messaging: one `Messages.json` call per recipient, HTTP Basic auth, account `Balance.json`. Status callbacks are HMAC-SHA1 (`X-Twilio-Signature`). `queued` or `sent` is not delivery. WhatsApp is not enabled.
- Africa's Talking bulk SMS: `POST /version1/messaging/bulk` with an `apiKey` header. Status codes 100–102 mean accepted. Handset delivery is a later unsigned `Success` callback. Balance is `GET /version1/user`. The sandbox host is separate. WhatsApp and inbox fetch are not enabled.
- Arkesel SMS v2: `POST /api/v2/sms/send` and `GET /api/v2/clients/balance-details` with an `api-key` header. Delivery callbacks carry `sms_id` and `status` and are not signed. `DELIVERED` means delivered.

## Decision
`App\Integrations\Sms\SmsTransmitter` is the only platform send entry point. Each provider is an adapter registered in `SmsProviderRegistry`. Adding a provider means a new adapter class and one line in that registry. Routes, webhook configuration, and the console tabs come from the registry.

Messages are stored before dispatch with an idempotency key. Status moves queued → submitted → accepted → delivered, or failed, or logged. Provider acceptance is not delivery. With no enabled active provider, the message is stored and written to the log.

Credentials are encrypted with the application key and edited by a platform super admin. They are not environment variables, because the operator must rotate a provider without a deploy. They are masked in the console and must not appear in logs, audit properties, or JavaScript. This refines ADR-0007 for SMS credentials only.

Callbacks use `spatie/laravel-webhook-client` on `routes/webhook.php`. Each provider has its own validator and job. All jobs update `sms_messages`. Providers that do not sign callbacks must present the secret path token. Twilio must also present a valid signature. Duplicate callback events are ignored. The stored callback URL has the token removed.

Wigal and Arkesel report an SMS bundle and a cash balance. Each has its own threshold. A warning is sent only when that reading is at or below its own threshold, and the e-mail quotes that reading. Twilio and Africa's Talking report an account balance only, so only the cash threshold applies. The warning goes to platform super admins by e-mail and the database. It is not sent by SMS.

Provider HTTP traffic is written to `storage/logs/sms-{provider}.log`. Sign-in events are written to `storage/logs/auth.log`. Those channels are not part of the default Laravel log.

`App\Contracts\SmsSender` stays bound to `LogSmsSender`. Phone confirmation does not call `SmsTransmitter`.

`sms_messages.counts_toward_subscription` is false for every row this transmitter writes. A future subscription module must ignore those rows.

The super-admin SMS overview reads those rows for the last seven days. Confirmed delivery is the share of answered messages that reached `delivered`. Accepted messages stay in their own count. Opening a row shows that message, its text, and the status history. The list itself keeps the number masked and leaves the text out.

## Consequences
Switching the active provider does not delete message history. Real delivery needs one enabled provider marked active. None active is a supported state and uses the log. Tests use fakes; no live provider is called from the test suite.
