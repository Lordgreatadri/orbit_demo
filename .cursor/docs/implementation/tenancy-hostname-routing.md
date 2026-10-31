# Hostname tenancy, branding, and white-label workspaces

- Date: 2026-10-30
- ADR: `0011-hostname-tenancy-and-branding.md`
- Assessment: `tenancy-routing-assessment.md`

## 1. What was inspected

PHP 8.4 (Herd), Laravel 13, existing `TenantContext` / `OrganizationScope`, session `SESSION_DOMAIN=null`, no tenancy package installed. Spatie Multitenancy 4.3.0 is compatible but assumes a single `domain` column and does not provide shared-schema scopes. stancl/tenancy defaults to database-per-tenant. **Neither package was installed.**

## 2. What was implemented

- Domain registry (`organization_domains`) with primary, alias, and custom-domain types.
- Hostname tenant resolution (`IdentifyTenant`) before route-model binding.
- Central host: public site, registration, `/admin`.
- Tenant host: branded login, `/org` workspace.
- Branding table + `BrandingService` (cached, invalidated on publish).
- Organisation and platform admin tabs: Profile, Domains, Branding.
- Host-only sessions; invitation/reset/verification URLs minted on the tenant origin when the account belongs to an organisation.
- `student` role hidden from non-educational organisations.
- Super-admin sidebar product-module placeholders restored.
- Sortable headers and visible record counts on large tables.
- Demo seed: Mobile Content Ltd (`mobilecontent` + `mcc` alias), EPA (`epa`), UPSA (`upsa`), Fictional Review Institute (`review`).

## 3. Files created (principal)

- `app/Tenancy/*`, `app/Http/Middleware/IdentifyTenant.php`, `EnsureTenantHost.php`, `EnsureCentralHost.php`
- `app/Models/OrganizationDomain.php`, `OrganizationBranding.php`
- `app/Actions/Tenancy/*`, `app/Http/Controllers/Tenancy/*`, `app/Http/Controllers/Branding/BrandingAssetController.php`
- `app/Jobs/Concerns/IsTenantAware.php`, `app/Jobs/Middleware/RestoreTenantContext.php`
- Migrations `2026_10_30_110000_*`, `2026_10_30_110100_*`
- Views under `resources/views/components/workspace/` and `*/organizations/{domains,branding}.blade.php`
- Tests under `tests/Feature/Tenancy/`
- Seeders `MobileContentOrganizationSeeder`, `ReviewInstituteSeeder`

## 4. Schema

`organization_domains`: uuid, organization_id, unique domain, type, is_primary, is_active, is_verified, verification_token, verified_at, created_by, updated_by.

`organization_brandings`: one row per organisation; live columns + JSON `draft`; published_at.

## 5. Packages installed

None. Octane was not added: Windows Herd already serves PHP 8.4; tenant context is a **scoped** binding with terminate/queue cleanup so it is Octane-safe when that runtime is adopted later.

## 6. Local hostnames (Herd)

Link the site as `orbit.test`. Herd resolves `*.orbit.test`.

| Host | Role |
| --- | --- |
| orbit.test | Central platform |
| mobilecontent.orbit.test | Mobile Content Ltd (primary) |
| mcc.orbit.test | Same organisation (alias) |
| epa.orbit.test | EPA Demo |
| upsa.orbit.test | UPSA Demo |
| review.orbit.test | Fictional Review Institute |

Do not set `SESSION_DOMAIN=.orbit.test`. Cookies stay host-only.

## 7. Review follow-up (2026-10-31)

- Existing organisations are provisioned by `OrganizationWorkspaceBackfillSeeder` (idempotent, called from `DatabaseSeeder` and the demo seeder). Migrations stay schema-only.
- `IdentifyTenant` forces the **current** hostname for in-page URLs so alias and secondary-central forms keep CSRF/session. Invitation and notification mail still use the primary origin (`Origin::of()`).
- Draft branding files live under `branding/draft/`; publish copies them to stable public slots and deletes unused files. Draft binaries are served only from `branding.preview-asset` (auth + `manageWorkspace`). `/branding/{slot}` reads published paths only.
- Changing the primary hostname forgets the branding cache. Activate/deactivate/delete/verify forget `TrustHostList::CUSTOM_DOMAINS_CACHE_KEY`.
- Cross-host sign-in is rejected **before** `Auth::attempt`, so history records `failed` rather than `login` + `logout`.

## 8. Outstanding risks

- Custom domains need a TXT record `_orbit-verify.{domain}` before activation; production DNS is owner-managed (`dns-and-tls.md`).
- After registration the responsible contact still has a session on the central host until they sign in on the workspace hostname.
- Branding assets are served only on the tenant host; guessing another organisation's UUID path returns 404.
