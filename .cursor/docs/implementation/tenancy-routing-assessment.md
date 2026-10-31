# Hostname tenancy — repository assessment

- Date: 2026-10-30
- Branch: `tenancy-domain-routing` (owner-managed)
- Status: Implemented after this assessment (same session)

## 1. What was inspected

| Area | Observed |
| --- | --- |
| PHP | Herd `php84` 8.4.x. PATH `php` is 8.2 and must not be used. `composer.json` requires `php: ^8.3`. |
| Laravel | `laravel/framework: ^13.17`. Bootstrap is the Laravel 13 fluent `bootstrap/app.php`. |
| Packages | `spatie/laravel-permission` ^8.3, `spatie/laravel-activitylog` ^5.1. **No tenancy package is installed.** |
| Tenancy today | Shared database, `organization_id` FKs, `BelongsToOrganization` + `OrganizationScope`, `TenantContext` singleton, `SetTenantContext` from the **authenticated user** (not hostname). ADR-0002. |
| Identity | One user belongs to one organisation **or** the platform (ADR-0009). No multi-org membership. |
| Hosts | `APP_URL` is a single origin. Session cookie `SESSION_DOMAIN=null` (host-only). No domain registry. |
| Organisation | Numeric PK + UUID, unique slug, type, status, timezone, `logo_path`, JSON `settings`. Slug is **not** a hostname. |
| Frontend | Blade + Tailwind 4 + Alpine. Tokens in `resources/css/app.css`. Layouts: public, auth, app. |
| Isolation tests | `tests/Feature/Tenancy/TenantIsolationTest.php` already covers scoped queries and cross-tenant 404s. |
| Demo seed | EPA (`epa-ghana-demo`) and UPSA (`upsa-demo`). No Mobile Content Ltd domain aliases. |

## 2. Package evaluation (Spatie v4 vs stancl vs native)

`spatie/laravel-multitenancy` **4.3.0** supports PHP 8.3–8.5 and Laravel 12–13. It was **not installed**.

| Candidate | Fit | Decision |
| --- | --- | --- |
| **spatie/laravel-multitenancy v4.3.0** | Compatible. `DomainTenantFinder` matches **one** `domain` column on the tenant model. No shared-schema query scopes. Queue awareness is a `makeCurrent()` lifecycle we already approximate. | **Do not install.** A custom finder plus `organization_domains` would still be required. Installing it would create a second current-tenant API beside `TenantContext`. |
| **stancl/tenancy** | Database-per-tenant default, tenancy bootstrappers, hostname identification. Conflicts with ADR-0002 (shared database, shared tables). | **Do not install.** |
| **Native resolver** | Extends the existing `TenantContext` / `OrganizationScope` / policies. Domain registry, aliases, verification, branding, and cache keys stay first-party. | **Chosen.** |

Laravel Octane was **not** installed as a local runtime. Windows Herd already serves PHP 8.4; Octane needs FrankenPHP/RoadRunner and would change the process model. Tenant context is now a **scoped** container binding with explicit `clear()` on request terminate and queue before/after/failing, which is the Octane-safe pattern. A feature test simulates two sequential requests to prove no leak.

## 3. Architecture (industry-standard split)

Three responsibilities, three modules — they cooperate, they are not one middleware:

1. **Tenant routing** — hostname → `organization_domains` → activate `TenantContext`.
2. **Tenant branding** — `organization_brandings` + `BrandingService` (cached, invalidated on publish).
3. **Tenant data isolation** — existing `organization_id`, global scope, policies, UUID bindings.

```
orbit.test                 central: public site, registration, platform console
epa.orbit.test             tenant B (EPA Demo)
upsa.orbit.test            tenant C (UPSA Demo)
mobilecontent.orbit.test   tenant A primary (Mobile Content Ltd)
mcc.orbit.test             tenant A alias (same organisation, different hostname)
attendance.example.test    future custom domain (verification required)
```

The organisation **slug is not the permanent URL**. Domains are registered rows. One organisation, many hostnames, one primary.

## 4. Gaps this increment closes

- Hostname is not used to identify a tenant.
- No domain registry, aliases, or custom-domain verification.
- Branding fields live (incompletely) on `organizations.logo_path` / `settings`.
- Super-admin sidebar dropped product-module placeholders.
- `student` is invitable in every organisation type (EPA should not see it).
- Table pages paginate but headers are not sortable; the footer hides when there is a single page.
- Invitation / verification / reset URLs are generated against `APP_URL`, not the tenant origin.
- `TenantContext` is a singleton (unsafe if a long-running worker skipped `terminate()`).

## 5. Non-negotiables preserved

- Shared database, shared application (ADR-0002).
- One user ↔ one organisation or platform (ADR-0009). Organisation switching is not introduced.
- Invitation-only membership; no open staff/student sign-up.
- Host-only session cookies. Sessions do **not** follow a redirect across hostnames.
- Platform console stays on the central host; organisation workspace stays on a tenant host.
- Attendance, QR, field, schedules, leave, notifications, analytics remain labelled placeholders.

## 6. Proposed files (implemented)

See `tenancy-hostname-routing.md` for the file list, schema, commands, and test results.
