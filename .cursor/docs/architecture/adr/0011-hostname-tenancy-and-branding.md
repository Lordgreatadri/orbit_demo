# ADR-0011: Hostname tenant routing and organisation branding

- Status: Accepted
- Date: 2026-10-30
- Relates to: ADR-0002 (shared-database tenancy), ADR-0008 (theme), ADR-0009 (account model)

## Context

ORBIT organisations need a white-label workspace URL (and aliases) plus their own logo, colours, and login copy, while remaining on one Laravel application and one database. Stage 0 deferred `spatie/laravel-multitenancy` and `stancl/tenancy` until subdomain-per-tenant was approved. It is now approved.

## Decision

1. Keep the shared-database model (ADR-0002). Do not install Spatie Multitenancy or stancl/tenancy.
2. Identify the current tenant from the **request hostname** via an `organization_domains` registry. Never from a user-supplied query parameter or untrusted header.
3. The organisation slug is an internal handle, not the public URL. An organisation may have many hostnames; exactly one is primary.
4. Platform (central) hosts serve public pages, organisation registration, and `/admin`. Tenant hosts serve branded sign-in and `/org`.
5. Branding lives in `organization_brandings`, not on the organisation row. A `BrandingService` is the only reader; Blade does not query branding tables.
6. Session cookies stay host-only (`SESSION_DOMAIN=null`). A user of Mobile Content Ltd signs in at `mobilecontent.orbit.test` or `mcc.orbit.test` as separate sessions.
7. Users still belong to exactly one organisation or the platform. Multi-org membership would require a new ADR.

## Consequences

- Local development needs a central host (`orbit.test`) and wildcard `*.orbit.test`. Herd's Nginx site and certificate already include that wildcard. On this Windows machine the hosts file lists `orbit.test` only, so each workspace name (`epa.orbit.test`, and the others) also needs a `127.0.0.1` line.
- A correct password entered on the platform site does not open a session there. The app sends the browser to the organisation's primary hostname with a one-minute handoff. Taking the token is serialized, so two requests cannot both sign in with it. A request on the wrong hostname does not use the token up. A correct password on another organisation's hostname stays a failed sign-in.
- Tests must issue tenant requests against the organisation's registered hostname.
- Invitation, password-reset, and verification links are minted against the organisation's primary origin so they land on the correct host.
- Published branding is cached for one hour as plain values, then rebuilt on read. The cache store does not restore PHP objects.
- Custom domains stay inactive until DNS verification succeeds.
- Platform administrators manage every organisation from the central console; they do not inherit tenant context from a tenant hostname.
