# Phase 1 — Progress Tracker

- Scope: `phase-1-plan.md` Stages 0–2 (assessment, public site, organisation registration & auth foundations).
- Toolchain: Herd PHP 8.4.27 (`C:\Users\rexme\.config\herd\bin\php84\php.exe`), Composer 2.10, Node 24 / npm 11.
- Tests: PHPUnit 12 on in-memory SQLite. Dev DB: PostgreSQL `orbit_gh` (from `.env`, owner-managed).
- Git: repository not initialised by Cursor; the owner initialises/commits.

Legend: `[x]` done and verified · `[~]` partial · `[ ]` not started

## Stage 0 — Assessment
- [x] Toolchain and skeleton assessment → `stage-0-repository-assessment.md`
- [x] Package-by-package dependency decision (§6 of the assessment)
- [x] ADR-0009 account model / auth / roles written
- [x] `.gitignore` no longer ignores `/.cursor/`; `AGENTS.md`/`CLAUDE.md` point to the governance pack

## Stage 1 — Design system and public site
- [x] Tokens + light (default) and deep-teal/midnight-plum (`data-theme="plum"`) themes in `resources/css/app.css`
- [x] Pre-paint theme script, `localStorage` persistence, `prefers-color-scheme` fallback (`resources/js/theme.js`)
- [x] Blade components: button, form/*, alert, badge, card, empty-state, page-header, flash, logo, theme-toggle
- [x] Layouts: `public`, `auth`, `app`, `error`
- [x] Pages: home, how-it-works, user guide, contact (honeypot + `throttle:contact`), terms (draft), privacy (draft)
- [x] Error pages 403/404/419/429/500/503
- [x] Tests: `PublicPagesTest`, `ContactFormTest`
- [x] Public visual refinement (2026-10-24 evening): mesh/grid backgrounds, larger laptop type scale (`html` 106.25% at 1024 px), official ORBIT icon + lockup, favicons on every layout, split-panel auth, hero/TOC treatment on inner public pages. Message copy unchanged.
- [ ] Replace text logo with owner-supplied `public/images/orbit-*.png` variant

## Stage 2 — Registration, authentication, tenant foundation
- [x] Schema: `organizations`, `users` (uuid + `organization_id`), `contact_messages`, `invitations`, spatie tables, `activity_log.organization_id`
- [x] `HasPublicUuid`, `BelongsToOrganization` + `OrganizationScope`, `TenantContext`, `IdentifyTenant` (hostname → tenant, runs **before** `SubstituteBindings`)
- [x] Organisation registration (`RegisterOrganization` action, transactional, creates org + admin + role + audit)
- [x] Login/logout (rate-limited, generic errors, inactive user / non-operational org blocked), `EnsureAccountIsOperational`
- [x] Password reset (broker, generic response, `throttle:password-reset`)
- [x] Email verification (signed URL; verifying the admin activates the organisation; toggle `ORBIT_REQUIRE_EMAIL_VERIFICATION`)
- [x] Roles & permissions seeded from `config/orbit.php`; platform/tenant boundary enforced in `User::assignRole/syncRoles`
- [x] Invitation-only membership: create (permission + policy), e-mail with single-use hashed token (7-day TTL), accept, revoke
- [x] App shell: dashboard empty state, organisation settings (`{organization:uuid}` + policy), members + pending invitations
- [x] Audit trail via `App\Support\Audit::log()` (activitylog + `organization_id`)
- [x] Tests: registration, login, password reset, verification, organisation settings isolation, invitations, tenant isolation, seeding
- [x] Local demo seed (not part of `DatabaseSeeder`): `php artisan db:seed --class=SystemDemoUserSeeder`. Fictional EPA Ghana and UPSA tenants, plus one account for every platform and tenant role. Password comes from `ORBIT_DEMO_PASSWORD`. Refuses `APP_ENV=production`.
- [x] Phone confirmation is required with e-mail before workspace access. One screen shows both checks. The code is logged by `LogSmsSender` until an SMS provider is bound to `App\Contracts\SmsSender`.
- [ ] Logo upload — deferred (Stage 3, needs media decision)

## Commands executed and observed results (latest run, 2026-10-24)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact` | **82 tests, 82 passed, 398 assertions** (9.8 s) |
| `php84 vendor/bin/pint` | 8 files fixed (imports/spacing; CRLF→LF in owner-added route stubs); clean afterwards |
| `npm run build` | ✓ built (3.7–4.5 s) — `app-*.css` 53 kB (9.9 kB gz), `app-*.js` 55 kB (19 kB gz); rebuilt after the contrast fix (`app-BbgY0VOy.css`) |
| `php84 artisan serve --port=8765` + Cursor browser | `/` and `/register` rendered; theme toggle, theme persistence across navigation, mobile nav verified visually; server stopped afterwards |
| `php84 artisan migrate --force` (PostgreSQL `orbit_gh`) | `0001_01_00_000000_create_countries_table` DONE (all others already applied) |
| `php84 artisan db:seed --force` (PostgreSQL) | `CountryTableSeeder` 293 ms, `RolesAndPermissionsSeeder` 97 ms; counts: countries 36, roles 6, permissions 6 |
| `php84 artisan route:list` | 27 application routes; `app/*` carry `web, auth, account.operational, verified` |

Earlier in the session: `migrate:fresh --seed` was run **once** against `orbit_gh` (destructive on that dev database — it had just been pointed at by the owner and contained no ORBIT data); subsequent runs used non-destructive `migrate` / `db:seed` only.

## Defects found by the test suite and fixed
1. `SubstituteBindings` ran before `SetTenantContext`, so UUID route binding was not tenant-scoped (policy still blocked access → 403 instead of 404). Fixed with `prependToPriorityList(SubstituteBindings::class, SetTenantContext::class)`; test now asserts 404 for a foreign invitation.
2. `EnsureAccountIsOperational` used the cached `organization` relation; a suspension mid-session was not detected for long-lived user instances. Now re-reads the organisation per request.
3. `AcceptInvitation` mass-assigned `email_verified_at` (not fillable by design). Now `forceFill`ed explicitly.
4. `App\Models\Activity` redeclared `$guarded` with a narrower visibility (fatal). Removed.
5. Test bootstrap depended on `DatabaseSeeder`, which the owner extended with `CountryTableSeeder`; tests now seed only roles/permissions, and `SeedingTest` covers the full seeder.

## Unresolved risks / decisions for the owner
- **Namespace layout**: owner-added code references `App\Modules\Shared\Domain\Models\…`; current code is in idiomatic `app/`. Decide before Stage 3 (see assessment §8).
- **`dyrynda/laravel-model-uuid`** was referenced by the owner's `Country` model but is not installed; `HasPublicUuid` is used instead. If the package is wanted project-wide, record it in an ADR and swap the trait once.
- **Spatie migration edits**: `uuid` columns on `roles`/`permissions`/`activity_log` are an owner decision; package upgrades that republish migrations will not include them.
- **Terms / Privacy** content is a draft for legal review.
- **Queue**: `InvitationNotification` is queued; local `QUEUE_CONNECTION=database` needs `php artisan queue:work` (or `QUEUE_CONNECTION=sync` in `.env`) for e-mails to leave.
- **Mail** is `log` driver locally; verification/invitation links appear in `storage/logs/laravel.log`.
- **Browser QA** of the public navigation covered 390, 768, 820, 1024, and 1280 px on 2026-10-26. Auth and app pages still need the same pass in both themes.
- `routes/webhook.php` is still an inert placeholder (no inbound providers in this phase). `routes/tenant.php` and `routes/superadmin.php` are wired.

## Commands executed and observed results (2026-10-26)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact tests/Feature/Database/DemoSeederTest.php` | **3 passed, 114 assertions** (2.7 s) |
| `php84 artisan test --compact` | **86 tests, 85 passed, 1 failed** (56.7 s). `InvitationTest` failed when the organisation name contained an apostrophe because the accept page escaped it twice. The view now passes the title and description unescaped, and the layout escapes them once. Re-ran `invitee_can_accept_a_valid_invitation` with the name `O'Connell Institute of Testing`: **1 passed** |
| `php84 vendor/bin/pint` on the new seeder and test paths | Passed, no changes |
| `php84 artisan db:seed --class=SystemDemoUserSeeder` (PostgreSQL) | Roles 532 ms, EPA organisation 81 ms, UPSA organisation 5 ms, seeder 4.4 s. Confirmed 2 organisations and 10 accounts, one for each role |
| Cursor browser, public home | No horizontal overflow at 390, 768, 820, 1024, and 1280. Portrait tablets use the menu; inline links start at 1024 px with a short Register label; the full label and theme text appear at 1280 px |
| `php84 artisan test --compact` (verification and platform directory) | **92 passed, 558 assertions** |
| `php84 artisan db:seed --class=SystemDemoUserSeeder` | Re-ran with `updateOrCreate`. Same 10 accounts, no duplicate-key error. Phone confirmation set on those accounts |

## Stage 3 — Workspace shell, access control, and structure (in progress)
- [x] Split routes by audience: `/org` tenant workspace, `/admin` platform console, `/app` account-neutral landing + profile
- [x] Fixed left sidebar (does not scroll with the page), expandable Users & access / Audit & security, mobile drawer, labelled coming-soon pages
- [x] Staff register separate from login accounts; org admins manage users, roles, and permissions; super admins see every account
- [x] Platform role/permission CRUD; org admins assign any tenant-scoped role including custom ones
- [x] Audit trail and sign-in history as separate pages (tenant and platform)
- [x] Mail header uses `public/images/brand/orbit-lockup-light.png` with `alt="ORBIT"`
- [x] Hostname tenancy + domain registry + organisation branding (ADR-0011). Spatie/stancl not installed; native resolver on shared DB.
- [x] Sortable table headers and visible “Showing x–y of z” counts on large lists
- [x] `student` role limited to educational institutions; super-admin product-module placeholders restored
- [x] Tenancy review pass (2026-10-31): idempotent workspace backfill seeder (not a migration), branding/trusted-host cache eviction, DNS label limit, draft vs published assets + authorised preview route, request-local origins for CSRF, host check before `Auth::attempt`, WCAG 4.5:1 ink, custom tenant roles in invitations, preview merge of nulls/assets, factory `updateOrCreate` reuse, colour-picker name, branding CSS after Vite, locked theme toggle, login panel image, central terms/privacy/guide links
- [ ] Attendance / QR / field / schedules / leave / notifications / analytics — labelled placeholders only

## Commands executed and observed results (2026-10-31 review pass)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | 1 unused import removed in `HostnameRoutingTest`; clean afterwards |
| `php84 artisan test --compact` | **175 tests, 175 passed, 1,306 assertions** |

## Recommended next task
Stage 4 — Attendance module (QR sessions and field check-in) using the seeded EPA demo locations and departments. Do not implement billing, payroll, BLE, biometrics, or live EPA integrations.

## Platform SMS providers (2026-11-02)
- [x] ADR-0012. `SmsTransmitter` is the platform entry point. Phone confirmation stays on `LogSmsSender`.
- [x] Adapters for Wigal Frog, Twilio, Africa's Talking, and Arkesel, registered in one place. Docs reviewed 2026-11-02.
- [x] Super-admin tabs: credentials encrypted and masked, one active sender, optional fallback, health, balance, test message.
- [x] Message row before dispatch, idempotency key, queued / submitted / accepted / delivered. Acceptance is not delivery. No active provider logs the message.
- [x] `routes/webhook.php` uses spatie/laravel-webhook-client. Each provider has its own validator and job and writes `sms_messages`.
- [x] Low balance notifies platform super admins by e-mail and the database, not by SMS.
- [x] `counts_toward_subscription` is false. These rows are platform-paid and must not be billed to an organisation later.
- [x] Live Wigal send confirmed by the operator (2026-11-02). The test suite still does not call provider APIs.
- [x] SMS bundle and cash balances use separate thresholds. A warning quotes only the purse that is at or below its own threshold.
- [x] Active navigation and tabs keep a dark wash in the plum theme, so the label stays readable.
- [x] `/admin/settings` opens the SMS provider screen. Provider traffic is logged to `storage/logs/sms-{provider}.log`, and sign-in events to `storage/logs/auth.log`.
- [x] `/admin/sms` is the super-admin transmission overview. Accepted messages are counted apart from delivered ones. Counts are aggregated in the database.
- [x] SMS review fixes: credentials stay out of flashed input, partial provider timeouts keep earlier acceptances, callbacks stay on the authenticated gateway, sends are claimed once, status changes lock the row, handoff tokens are consumed once, Twilio sender IDs allow a Messaging Service SID, batches are validated before insert, rate limits cover fallback and oversized batches, and database notifications keep a numeric id.

## Commands executed and observed results (2026-11-02 platform SMS)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact` | **194 tests, 194 passed, 1,391 assertions**, 95.8s |
| `php84 artisan migrate --force` | Applied `organization_domains`, `organization_brandings`, `webhook_calls`, SMS tables, and `notifications` |
| `php84 artisan sms:sync-providers` | 4 providers registered; existing settings left in place |
| `php84 artisan route:list --path=webhooks` | POST `webhooks/sms/{wigal,twilio,africastalking}/{token}`; GET and POST `webhooks/sms/arkesel/{token}` |
| `composer update --lock --no-scripts` then `composer validate --no-check-publish --no-check-version` | Lock hash aligned with `spatie/laravel-webhook-client: ^3.7`. Composer reports valid |
| Signed-in HTTP check against `php artisan serve` on `127.0.0.1:8000` | Super admin sees four tabs (Africa's Talking, Arkesel, Twilio, Wigal Frog). Saving a sender ID round-trips and was reverted. Secret inputs stay empty. Health on a disabled provider returns “Turn the provider on before checking it.” `platform.support` receives 403. Unauthenticated `/admin/sms-providers` redirects to login |
| `php84 artisan test --compact` on SMS, overview, provider settings, and login | **54 tests, 54 passed, 269 assertions** |
| `php84 artisan migrate --force` | Applied `2026_11_02_140000_widen_sms_sender_and_index_messages` |
