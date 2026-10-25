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
- [x] `HasPublicUuid`, `BelongsToOrganization` + `OrganizationScope`, `TenantContext`, `SetTenantContext` (runs **before** `SubstituteBindings`)
- [x] Organisation registration (`RegisterOrganization` action, transactional, creates org + admin + role + audit)
- [x] Login/logout (rate-limited, generic errors, inactive user / non-operational org blocked), `EnsureAccountIsOperational`
- [x] Password reset (broker, generic response, `throttle:password-reset`)
- [x] Email verification (signed URL; verifying the admin activates the organisation; toggle `ORBIT_REQUIRE_EMAIL_VERIFICATION`)
- [x] Roles & permissions seeded from `config/orbit.php`; platform/tenant boundary enforced in `User::assignRole/syncRoles`
- [x] Invitation-only membership: create (permission + policy), e-mail with single-use hashed token (7-day TTL), accept, revoke
- [x] App shell: dashboard empty state, organisation settings (`{organization:uuid}` + policy), members + pending invitations
- [x] Audit trail via `App\Support\Audit::log()` (activitylog + `organization_id`)
- [x] Tests: registration, login, password reset, verification, organisation settings isolation, invitations, tenant isolation, seeding
- [ ] Phone OTP verification — deferred until an SMS provider is approved (ADR-0007)
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
- **Browser QA** covered only the landing and registration pages; the remaining public, auth and app pages need the same pass (keyboard, screen-reader labels, 390 px layout).
- `routes/tenant.php`, `routes/superadmin.php`, `routes/webhook.php` are inert placeholders; wire them deliberately in Stage 3.

## Recommended next task
Stage 3 — Tenant security hardening and EPA demo foundation: (1) decide the `app/Modules` layout and apply it in one mechanical move; (2) platform admin area (`routes/superadmin.php`) with `platform_super_admin` guard, organisation list/suspend, impersonation decision; (3) fictional "Ghana EPA demo" seed (organisation, members, districts from `ghana-districts.json`); (4) browser QA pass of Stage 1–2 pages in both themes.
