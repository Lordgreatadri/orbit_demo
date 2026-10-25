# Integration Strategy

All integrations must be decoupled from core ORBIT business logic. Build only integrations required by the active phase and only after credentials, commercial access, and API requirements are supplied.

## Pattern
- Define a domain-facing contract for a capability (for example `SmsProvider`).
- Implement provider-specific adapters in a dedicated namespace/folder.
- Select provider via validated configuration, not scattered conditionals in controllers.
- Keep credentials in environment variables or a secret manager.
- Apply timeouts, safe retries/backoff, idempotency where possible, and provider-specific error mapping.
- Queue slow outbound work and store delivery status without logging secrets.
- Use fake adapters in automated tests and contract tests for real providers.
- Add a provider only after reviewing its current official documentation and terms.

## SMS / messaging candidates from the source documents
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

These are source-provided reference links, not confirmation that any provider is configured or currently selected. Verify documentation, endpoint behaviour, authentication, rate limits, delivery receipts, and pricing before implementation.

## Email
Use Laravel's mail abstraction. Configure a supported provider per environment. Do not hard-code a vendor into domain workflows.

## Payments
Payment APIs are intentionally deferred until the product owner supplies provider requirements and credentials. Do not implement billing/subscription flows in the EPA demo.

## Maps/geolocation
Browser geolocation requires explicit permission and secure context in production. Server-side geofence validation remains authoritative. If an external map provider is used, isolate its SDK/configuration and provide a useful fallback when credentials are absent.
