# Provider Integration Checklist

Before adding a provider:
- [ ] Confirm the business capability and owner-approved scope.
- [ ] Read current official API documentation and note the review date.
- [ ] Confirm sandbox access, credentials, pricing, quotas, and data residency/retention implications.
- [ ] Define a provider-neutral contract and provider adapter.
- [ ] Store credentials in environment/secret management.
- [ ] Define timeout, retry/backoff, idempotency, throttling, and failure behaviour.
- [ ] Decide which response metadata is stored and how secrets/personal data are redacted.
- [ ] Queue work when appropriate and make jobs safe to retry.
- [ ] Add fake-provider tests and adapter contract tests.
- [ ] Add monitoring, delivery status, and an operator runbook.
- [ ] Confirm provider switching does not require changes to core domain logic.
- [ ] Update documentation and relevant ADRs.
