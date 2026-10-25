# Stage 0 — Repository Assessment and Phase 1 File Plan

- Date: 2026-10-24
- Author: Senior Laravel architect (Cursor session)
- Status: Delivered; Phase 1 Stage 1–2 implementation follows this document

## 1. Existing architecture and technology stack

### What was in the repository before this session
The workspace `F:\PROJECTS\MCC_PROJECTS\MCCAI\orbit` contained **only** the engineering pack:
`.cursor/README.md`, `.cursor/rules/*.mdc`, `.cursor/docs/**`, and two source `.docx` documents.
There was no `composer.json`, no application code, no migrations, no tests, no `.git` directory.
There was therefore nothing to "preserve" or "upgrade"; the instruction to create a fresh Laravel 13
project in the root was followed.

### Toolchain found on the machine (observed, not assumed)
| Tool | Observed | Implication |
| --- | --- | --- |
| `php` on PATH | PHP 8.2.26 (WAMP, ZTS) | **Cannot run Laravel 13** (`php: ^8.3`). Do not use for this project. |
| Herd PHP | 8.3.36, **8.4.27**, 8.5 (OPcache load error), 8.6.0RC2 | Project standardised on `C:\Users\rexme\.config\herd\bin\php84\php.exe`. Extensions present: pdo_sqlite, pdo_pgsql, pdo_mysql, redis, intl, gd, zip, mbstring, openssl, bcmath, curl, fileinfo. |
| Composer | 2.10.3 (`herd\bin\composer.phar`) | OK. Note `composer.bat` on PATH resolves through PHP 8.2 — call the phar with php84 explicitly. |
| Laravel installer | 5.32.0 | Not used; `composer create-project` used for a controlled, non-interactive, no-git install. |
| Node / npm | 24.19.0 / 11.17.0 | OK for Vite 8 / Tailwind 4. |
| Git | 2.48.1 | Repository is **not** initialised. Per governance, Cursor does not initialise/commit; the user owns this. |
| PostgreSQL | Services `postgresql-x64-12…16` running; port 5432 listening | Preferred production DB is available locally, but **credentials are unknown and were not invented**. Development default is SQLite until the user supplies `DB_*` values. |
| MySQL/MariaDB | WAMP MariaDB on 3306 | Not used. |
| Redis | No listener on 6379 | Cache/queue/session use the `database` driver in local; Redis deferred. |

### Skeleton created
`laravel/laravel` **v13.x** (framework `^13.17`, resolved 13.38), PHP `^8.3`, PHPUnit 12.5, Pint 1.27,
Pail, Tinker, Vite 8, Tailwind CSS 4 (`@tailwindcss/vite`), `laravel-vite-plugin` 3.2.
Defaults: SQLite DB, `database` session/cache/queue drivers, `log` mailer.
Laravel 13 conventions in the skeleton: model `#[Fillable]`/`#[Hidden]` attributes, `casts()` method,
`bootstrap/app.php` fluent configuration, `health: '/up'` endpoint.

## 2. What exists and is reused
- Laravel 13 skeleton: `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs` migrations; `User` model;
  `UserFactory`; PHPUnit config with in-memory SQLite; Vite/Tailwind pipeline; Pint.
- Framework primitives used instead of extra packages: session auth guard, `RateLimiter`/`throttle`,
  `Password` broker (expiring, single-use, throttled tokens), `MustVerifyEmail` + signed URLs, policies/gates,
  `HasUuids` (adapted to a secondary `uuid` column), Form Requests, notifications/mail, queues.

## 3. Missing requirements and conflicts found

### Missing (expected — greenfield)
Everything in Phase 1: design system/theme, public pages, contact form, organisation registration,
auth flows, tenant foundation, roles, invitations, app shell, tests, seeders.

### Conflicts and discrepancies
1. **`.gitignore` ignores `/.cursor/`** in the Laravel 13 skeleton. The engineering pack lives there and is the
   project specification. Fixed: the `/.cursor/` line was removed so rules/docs are versioned.
2. **Spec path mismatch**: governance rules reference `docs/…` at the repository root, but the pack is at
   `.cursor/docs/…` and no root `docs/` exists. This session treats `.cursor/docs` as the specification and
   writes implementation documents there. Recommendation: the user decides whether to move/symlink to `/docs`.
3. **Package list vs. ADRs** (see §6): `stancl/tenancy` **and** `spatie/laravel-multitenancy` are mutually
   exclusive approaches and both conflict with ADR-0002 (shared DB, `organization_id`, no tenancy package
   required). `spatie audit log` and `spatie/laravel-activitylog` are the same package. `endroid/qr-code` and
   `giauphan/laravel-qr-code` overlap. `babdev/laravel-twilio` and `laravel-notification-channels/twilio`
   overlap and pre-select a provider that ADR-0007 says is only a candidate.
4. **Phone verification** (ADR-0005 "verify email & phone") needs an SMS provider that is not configured.
   Phone is captured and `phone_verified_at` is reserved; OTP verification is deferred until a provider is
   approved (ADR-0007).
5. **PostgreSQL credentials** are not available; migrations are written portably and verified on SQLite.
   They must be re-run against PostgreSQL before any deployment (risk R5 below).
6. **Skeleton `AGENTS.md`/`CLAUDE.md`** instruct agents to install Laravel Boost. Replaced with a short pointer
   to the ORBIT governance pack; Boost is optional tooling and was not installed.

## 4. Risks
| # | Area | Risk | Mitigation in Phase 1 |
| --- | --- | --- | --- |
| R1 | Tenancy | Tenant leak through unscoped queries or client-supplied IDs | `TenantContext` resolved from the authenticated user; `BelongsToOrganization` global scope + auto-fill on create; policies compare `organization_id`; UUID route binding; cross-tenant feature tests. |
| R2 | Auth | Account enumeration, brute force, token reuse | Generic reset response, `throttle` on login/register/reset/contact/invitation, Laravel password broker, session regeneration, invitation tokens hashed at rest + expiry + single use. |
| R3 | Security | Open member sign-up | Only `/register` exists and it always creates an organisation + administrator; invitation acceptance is the only other account-creation path; tests assert this. |
| R4 | DB integrity | Partial organisation/user records on failure | Registration and invitation acceptance run in `DB::transaction`; tests assert no orphan rows after validation failure. |
| R5 | DB portability | SQLite in dev/test vs PostgreSQL in prod (case-sensitivity of unique email, index behaviour) | Emails normalised to lower-case before validation/storage; migrations use portable column types; run `migrate:fresh` on PostgreSQL when credentials are supplied. |
| R6 | Performance | Unbounded lists | Members/invitations paginated; indexes on `organization_id`, status, token hash, expiry. |
| R7 | Maintainability | Framework-hidden auth behaviour | Auth controllers are explicit, small, Breeze-style classes in `app/Http/Controllers/Auth` using framework primitives; MFA extension point noted (ADR-0009). |
| R8 | Ops | No Redis; `database` queue driver | Acceptable locally; `QUEUE_CONNECTION=redis` + worker required before production. |
| R9 | Legal | Terms/Privacy content | Pages are clearly marked "draft for owner/legal review". |
| R10 | Tooling | Wrong PHP on PATH | Documented; `composer.json` enforces `php: ^8.3`; README documents Herd php84 usage. |

## 5. Files created/modified for Phase 1 (Stages 1–2)
See the "Changed files" list in `phase-1-progress.md`, which is kept current as work lands. Summary:

- **Config**: `config/orbit.php` (roles, permissions, organisation types, terms version, verification toggle),
  `config/permission.php`, `config/activitylog.php`, `.env.example`.
- **Database**: migrations for `organizations`, altered `users`, `contact_messages`, `invitations`,
  spatie permission + activity log tables; `RolesAndPermissionsSeeder`; factories.
- **Domain**: `App\Models\{Organization,User,ContactMessage,Invitation}`,
  `App\Models\Concerns\{HasPublicUuid,BelongsToOrganization}`, `App\Tenancy\TenantContext`,
  `App\Enums\{OrganizationStatus,OrganizationType,UserStatus,InvitationStatus}`,
  `App\Actions\Organizations\RegisterOrganization`, `App\Actions\Invitations\{CreateInvitation,AcceptInvitation}`.
- **HTTP**: `routes/web.php`, `routes/auth.php`, `routes/app.php`; middleware `SetTenantContext`,
  `EnsureAccountIsOperational`; controllers for public pages, contact, auth (login, register, password reset,
  verification, confirm), dashboard, organisation settings, members, invitations; Form Requests; policies.
- **Views**: layouts (`public`, `app`, `auth`), components (button, input, select, checkbox, alert, badge,
  empty-state, theme-toggle, nav), public pages, auth pages, app pages, error pages.
- **Assets**: `resources/css/app.css` (tokens + themes), `resources/js/app.js` (Alpine + theme).
- **Tests**: `tests/Feature/{PublicPages,ContactForm,Auth/*,Organization/*,Invitations/*,Tenancy/*}`.

## 6. Dependencies — decision per requested package
Governance requires each package to be justified by a Phase-1 use case and checked for overlap.

| Package | Decision | Reason |
| --- | --- | --- |
| spatie/laravel-permission ^8.3 | **Installed** | Role/permission foundation required in Phase 1; maintained; Laravel 13 compatible. Used **without** teams mode because one user belongs to exactly one organisation (ADR-0009); tenancy is enforced by `organization_id`, not role scoping. |
| spatie/laravel-activitylog ^5.1 | **Installed** | Audit trail for registration, login, invitations, settings changes (requirements 01/06). "spatie audit log" in the list is this package. |
| alpinejs (npm) | **Installed** | Theme toggle, mobile nav, menus (ADR-0004). |
| dyrynda/laravel-model-uuid | Not installed | Framework `HasUuids` configured for a secondary `uuid` column does the job (`HasPublicUuid` trait, ~20 lines). |
| stancl/tenancy | Not installed | Defaults to multi-database/domain tenancy; contradicts ADR-0002. Would require a new ADR. |
| spatie/laravel-multitenancy | Not installed | Mutually exclusive with the above; ADR-0002 model is a FK + scope, which is implemented natively and is fully testable. Reconsider only if subdomain-per-tenant is approved. |
| spatie/laravel-medialibrary | Deferred (Stage 3) | Logo upload "only if straightforward"; not in Stage 1–2. |
| spatie/laravel-settings | Deferred | Organisation settings fit a small JSON column / dedicated columns for now. |
| maatwebsite/excel, barryvdh/laravel-dompdf | Deferred (Stage 4 exports) | No export feature in Phase 1. |
| endroid/qr-code | Deferred (Stage 4 attendance) | Preferred over `giauphan/laravel-qr-code` (thin wrapper, lower maintenance). |
| giauphan/laravel-qr-code | Not recommended | Overlaps endroid. |
| firebase/php-jwt | Not recommended now | QR session tokens can use signed URLs / hashed random tokens; revisit for API. |
| babdev/laravel-twilio, laravel-notification-channels/twilio | Deferred | SMS provider not selected (ADR-0007). Add behind an `SmsProvider` contract when chosen. |
| spatie/laravel-webhook-client | Deferred | Needed only for provider delivery receipts. |
| predis/predis | Deferred | `ext-redis` is present in Herd PHP; add predis only if a deployment lacks the extension. |
| spatie/laravel-backup | Deferred (Stage 5) | Production readiness. |
| stevebauman/location | Not recommended | IP geolocation is not attendance verification; GPS comes from the browser. |
| lab404/laravel-impersonate | Deferred (Stage 3) | Platform support feature; must be audited and permission-gated. |
| spatie/laravel-query-builder | Deferred | For `/api/v1` filtering when the API is implemented. |
| laravel/boost (skeleton suggestion) | Not installed | Optional agent tooling. |

## 7. Staged test and verification plan
1. **Baseline**: `php artisan test` on the fresh skeleton (expect example tests green).
2. **Stage 1 (public)**: feature tests for each public route (200 + key copy), contact form validation,
   honeypot, throttle, notification dispatch; `npm run build` succeeds; manual responsive/keyboard check.
3. **Stage 2 (auth/onboarding)**: registration happy path (org + admin + role + activity + login),
   validation failure leaves no rows, duplicate email, terms consent, throttle; login/logout, failed login,
   lockout; password reset request/complete/invalid token; email verification activates organisation;
   protected routes redirect guests; no member self-registration route.
4. **Tenant foundation**: organisation settings via UUID — own org OK, other org 403, malformed UUID 404,
   member without permission 403; invitation create (permission), accept (valid), expired/used/revoked/
   cross-tenant rejected; platform role cannot be assigned to a tenant user.
5. **Quality gates**: `vendor/bin/pint --test`, `php artisan test`, `npm run build`,
   `php artisan migrate:fresh --seed` on SQLite; same on PostgreSQL once credentials exist.
6. Report commands and actual outcomes in `phase-1-progress.md`.

## 8. Addendum — files changed concurrently by the product owner during the session
While Stage 1–2 was being implemented, the following changes appeared in the workspace from outside the
Cursor session. They were **not overwritten**; where they broke the build, the smallest compatible fix was
applied and is listed here so nothing is hidden.

| Change observed | Effect | Action taken |
| --- | --- | --- |
| `.env` switched to `DB_CONNECTION=pgsql`, database `orbit_gh` (credentials never displayed by Cursor) | Resolves conflict §3.5: PostgreSQL is now the dev database. `migrate:fresh --seed` / `migrate` / `db:seed` ran successfully against it. | Documented. **Note:** `migrate:fresh` dropped and recreated all tables in `orbit_gh` once during the session. |
| Published spatie migrations (`create_permission_tables`, `create_activity_log_table`) edited to add `uuid` columns | Package models would insert NULL into NOT NULL `uuid` → seeding failed. | Added `App\Models\{Role,Permission,Activity}` extending the package models with `HasPublicUuid`; pointed `config/permission.php`, `config/activitylog.php` and the seeder at them. |
| `database/migrations/0001_01_00_000000_create_countries_table.php`, `database/seeders/CountryTableSeeder.php`, `app/Models/Country.php`, `database/data/ghana-districts.json` | Reference data ported from another project. `Country` used `Dyrynda\Database\Support\GeneratesUuid` (package **not installed**, see §6) and the seeder first referenced `App\Modules\Shared\Domain\Models\Country`, which does not exist. | Swapped the trait for the project's `HasPublicUuid` (same `uuid` column semantics). Tests seed only `RolesAndPermissionsSeeder`; `tests/Feature/Database/SeedingTest` covers the full `DatabaseSeeder`. `ghana-districts.json` is unused so far. |
| `database/seeders/DatabaseSeeder.php` now calls `CountryTableSeeder` then `RolesAndPermissionsSeeder` | Fine; both are idempotent. | None. |
| `routes/tenant.php`, `routes/superadmin.php`, `routes/webhook.php` (empty `org/`, `admin/` groups; commented `Route::webhooks`) | Not registered in `bootstrap/app.php`, so inert. `Route::webhooks` requires `spatie/laravel-webhook-client` (deferred). | Left as-is; Pint normalised line endings only. Register them deliberately when Stage 3 (platform admin) starts. |
| `public/images/orbit-*.png` logos | Brand assets. | Not yet wired into `<x-logo>` (currently a text/SVG mark); trivial follow-up once the owner picks the variant. |
| `App\Modules\Shared\Domain\Models\…` namespace signal | Suggests an intended `app/Modules/<Module>/Domain` layout (ADR-0001 modular monolith). | Current code uses the idiomatic `app/` layout. Moving to `app/Modules` is a mechanical refactor best done in one dedicated step with an ADR-0001 amendment, **before** Stage 3 adds more code. Decision needed from the owner. |
