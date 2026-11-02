# Integration Strategy

All integrations must be decoupled from core ORBIT business logic. Build only integrations required by the active phase and only after credentials, commercial access, and API requirements are supplied.

## Pattern
- Define a domain-facing contract for a capability (for example `SmsProvider`).
- Implement provider-specific adapters in a dedicated namespace/folder.
- Select provider via validated configuration, not scattered conditionals in controllers.
- Keep credentials in environment variables or a secret manager. SMS provider credentials are the exception in ADR-0012: a platform super admin stores them encrypted, and they are never written to source, logs, or the browser.
- Apply timeouts, safe retries/backoff, idempotency where possible, and provider-specific error mapping.
- Queue slow outbound work and store delivery status without logging secrets.
- Use fake adapters in automated tests and contract tests for real providers.
- Add a provider only after reviewing its current official documentation and terms.

## SMS
Reviewed 2026-11-02 and implemented behind `App\Integrations\Sms\SmsTransmitter` (ADR-0012).

Platform SMS is paid by ORBIT. `counts_toward_subscription` is false on every row the transmitter writes, so a later organisation subscription must not bill these messages. Phone confirmation stays on `LogSmsSender` and does not use this transmitter. With no enabled active provider, messages are stored and written to the log.

| Provider | Send | Balance | Callback |
| --- | --- | --- | --- |
| Wigal Frog | `POST https://frogapi.wigal.com.gh/api/v3/sms/send` (`API-KEY`, `USERNAME`). `ACCEPTD` is acceptance. | `GET /api/v3/balance` | Unsigned dashboard POST. `DELIVRD` is delivery. Secret path token. |
| Twilio | `POST /2010-04-01/Accounts/{Sid}/Messages.json`, one recipient per call. | `GET .../Balance.json` (account balance). | `X-Twilio-Signature` plus the path token. |
| Africa's Talking | `POST /version1/messaging/bulk`, `apiKey` header, `enqueue=1`. Codes 100–102 are acceptance. | `GET /version1/user` | Unsigned form POST. `Success` is delivery. Secret path token. |
| Arkesel | `POST https://sms.arkesel.com/api/v2/sms/send`, `api-key` header. | `GET /api/v2/clients/balance-details` | `sms_id` and `status` query. `DELIVERED` is delivery. Secret path token. |

No published numeric rate limit was found for Wigal, Africa's Talking, or Arkesel on that date. Each adapter has a configurable requests-per-minute guard (defaults 30, Twilio 60) and treats HTTP 429 and 5xx as retryable. Rejected credentials, sender IDs, and numbers are not retried.

WhatsApp, voice, and provider OTP products were reviewed and are not wired. A new provider is a new adapter registered in `SmsProviderRegistry`.

## SMS / messaging reference links
- Frog Wigal: https://frogdocs.wigal.com.gh/send_personalized.html
- Frog Wigal general send: https://frogdocs.wigal.com.gh/send_general.html
- Frog Wigal account balance: https://frogdocs.wigal.com.gh/get_account_balance.html
- Africa's Talking SMS overview: https://developers.africastalking.com/docs/sms/overview
- Africa's Talking bulk SMS: https://developers.africastalking.com/docs/sms/sending/bulk
- Africa's Talking fetch messages: https://developers.africastalking.com/docs/sms/fetch_messages
- Africa's Talking WhatsApp: https://developers.africastalking.com/docs/whatsapp/send_message
- Arkesel developer API: https://arkesel.com/developer-api/
- Arkesel docs: https://developers.arkesel.com/
- Arkesel SMS v2 send: https://developers.arkesel.com/#/sms-v2/send_sms
- Arkesel balance: https://developers.arkesel.com/#/sms-v2/check_client_balance
- Arkesel SMS details: https://developers.arkesel.com/#/sms-v2/sms_details
- Twilio Messaging: https://www.twilio.com/docs/messaging/quickstart
- Twilio WhatsApp: https://www.twilio.com/docs/whatsapp/quickstart

These links were the review set for ADR-0012. They are not a promise that a provider has credentials in a given environment.

## Email
Use Laravel's mail abstraction. Configure a supported provider per environment. Do not hard-code a vendor into domain workflows.

## Payments
Payment APIs are intentionally deferred until the product owner supplies provider requirements and credentials. Do not implement billing/subscription flows in the EPA demo.

## Maps/geolocation
Browser geolocation requires explicit permission and secure context in production. Server-side geofence validation remains authoritative. If an external map provider is used, isolate its SDK/configuration and provide a useful fallback when credentials are absent.
