# ADR-0007: Decoupled external integrations

- Status: Accepted
- Date: 2026-10-24

## Decision
External providers are implemented behind domain-facing contracts and provider adapters. Provider selection and credentials are configuration-driven; secrets are stored in environment/secret management, not in source. Failures, timeouts, retries, rate limits, and provider-specific response mapping are handled at the adapter boundary.

Initial integrations are not assumed to be live. The EPA demo uses fictional data and does not connect to EPA systems. SMS providers named in source documents are candidates only; payment providers will be supplied later.

## Consequences
Core business logic remains provider-neutral. Contract tests and fake adapters allow development without production credentials. A new provider should not require changes throughout controllers and domain workflows.
