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

A batch is checked in full before any row is stored. Messages are then stored with an idempotency key and dispatched after that write commits. Status moves queued → submitted → accepted → delivered, or failed, or logged. Each change re-reads the row under a lock, so a delivery report is not overwritten by an older send result. A worker claims a message before calling the provider, so a second run cannot send it at the same time. Provider acceptance is not delivery. With no enabled active provider, the message is stored and written to the log.

A timeout is caught around each provider request. Recipients or text groups already accepted stay accepted; only the request that timed out is retried. The per-minute guard counts provider HTTP calls, and a batch larger than the remaining allowance is split. Fallback delivery uses that same guard. On the sync queue, a rate-limited remainder is failed, because that queue cannot run the job again later.

Credentials are encrypted with the application key and edited by a platform super admin. They are not environment variables, because the operator must rotate a provider without a deploy. They are masked in the console and must not appear in logs, audit properties, or JavaScript. A failed save does not flash the `credentials` input into the session. This refines ADR-0007 for SMS credentials only.

Callbacks use `spatie/laravel-webhook-client` on `routes/webhook.php`. Each provider has its own validator and job. All jobs update `sms_messages`. A callback is applied only to a message that belongs to the gateway that authenticated, including an earlier send attempt recorded for that gateway. The `uuid` column is compared only when the correlation id is a UUID. Providers that do not sign callbacks must present the secret path token. Twilio must also present a valid signature. Duplicate callback events are ignored. The stored callback URL has the token removed.

Wigal and Arkesel report an SMS bundle and a cash balance. Each has its own threshold. A warning is sent only when that reading is at or below its own threshold, and the e-mail quotes that reading. Twilio and Africa's Talking report an account balance only, so only the cash threshold applies. The warning goes to platform super admins by e-mail and the database. The notification row keeps a numeric id and stores Laravel's notification id in `uuid`. It is not sent by SMS.

Provider HTTP traffic is written to `storage/logs/sms-{provider}.log`. Sign-in events are written to `storage/logs/auth.log`. Those channels are not part of the default Laravel log.

`App\Contracts\SmsSender` stays bound to `LogSmsSender`. Phone confirmation does not call `SmsTransmitter`.

`sms_messages.counts_toward_subscription` is false for every row this transmitter writes. A future subscription module must ignore those rows.

The super-admin SMS overview aggregates those rows in the database for the last seven days. `created_at` is indexed for that window. Confirmed delivery is the share of answered messages that reached `delivered`. Accepted messages stay in their own count. Opening a row shows that message, its text, and the status history. The list itself keeps the number masked and leaves the text out.

Twilio's sender field accepts a Messaging Service SID (34 characters) as well as an E.164 number.

## Consequences
Switching the active provider does not delete message history. Real delivery needs one enabled provider marked active. None active is a supported state and uses the log. Tests use fakes; no live provider is called from the test suite.
