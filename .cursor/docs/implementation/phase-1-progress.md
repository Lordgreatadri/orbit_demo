# Phase 1 â€” Progress Tracker

- Scope: `phase-1-plan.md` Stages 0â€“2 (assessment, public site, organisation registration & auth foundations).
- Toolchain: Herd PHP 8.4.27 (`C:\Users\rexme\.config\herd\bin\php84\php.exe`), Composer 2.10, Node 24 / npm 11.
- Tests: PHPUnit 12 on in-memory SQLite. Dev DB: PostgreSQL `orbit_gh` (from `.env`, owner-managed).
- Git: repository not initialised by Cursor; the owner initialises/commits.

Legend: `[x]` done and verified Â· `[~]` partial Â· `[ ]` not started

## Stage 0 â€” Assessment
- [x] Toolchain and skeleton assessment â†’ `stage-0-repository-assessment.md`
- [x] Package-by-package dependency decision (Â§6 of the assessment)
- [x] ADR-0009 account model / auth / roles written
- [x] `.gitignore` no longer ignores `/.cursor/`; `AGENTS.md`/`CLAUDE.md` point to the governance pack

## Stage 1 â€” Design system and public site
- [x] Tokens + light (default) and deep-teal/midnight-plum (`data-theme="plum"`) themes in `resources/css/app.css`
- [x] Pre-paint theme script, `localStorage` persistence, `prefers-color-scheme` fallback (`resources/js/theme.js`)
- [x] Blade components: button, form/*, alert, badge, card, empty-state, page-header, flash, logo, theme-toggle
- [x] Layouts: `public`, `auth`, `app`, `error`
- [x] Pages: home, how-it-works, user guide, contact (honeypot + `throttle:contact`), terms (draft), privacy (draft)
- [x] Error pages 403/404/419/429/500/503
- [x] Tests: `PublicPagesTest`, `ContactFormTest`
- [x] Public visual refinement (2026-10-24 evening): mesh/grid backgrounds, larger laptop type scale (`html` 106.25% at 1024 px), official ORBIT icon + lockup, favicons on every layout, split-panel auth, hero/TOC treatment on inner public pages. Message copy unchanged.
- [ ] Replace text logo with owner-supplied `public/images/orbit-*.png` variant

## Stage 2 â€” Registration, authentication, tenant foundation
- [x] Schema: `organizations`, `users` (uuid + `organization_id`), `contact_messages`, `invitations`, spatie tables, `activity_log.organization_id`
- [x] `HasPublicUuid`, `BelongsToOrganization` + `OrganizationScope`, `TenantContext`, `IdentifyTenant` (hostname â†’ tenant, runs **before** `SubstituteBindings`)
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
- [ ] Logo upload â€” deferred (Stage 3, needs media decision)

## Commands executed and observed results (latest run, 2026-10-24)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact` | **82 tests, 82 passed, 398 assertions** (9.8 s) |
| `php84 vendor/bin/pint` | 8 files fixed (imports/spacing; CRLFâ†’LF in owner-added route stubs); clean afterwards |
| `npm run build` | âœ“ built (3.7â€“4.5 s) â€” `app-*.css` 53 kB (9.9 kB gz), `app-*.js` 55 kB (19 kB gz); rebuilt after the contrast fix (`app-BbgY0VOy.css`) |
| `php84 artisan serve --port=8765` + Cursor browser | `/` and `/register` rendered; theme toggle, theme persistence across navigation, mobile nav verified visually; server stopped afterwards |
| `php84 artisan migrate --force` (PostgreSQL `orbit_gh`) | `0001_01_00_000000_create_countries_table` DONE (all others already applied) |
| `php84 artisan db:seed --force` (PostgreSQL) | `CountryTableSeeder` 293 ms, `RolesAndPermissionsSeeder` 97 ms; counts: countries 36, roles 6, permissions 6 |
| `php84 artisan route:list` | 27 application routes; `app/*` carry `web, auth, account.operational, verified` |

Earlier in the session: `migrate:fresh --seed` was run **once** against `orbit_gh` (destructive on that dev database â€” it had just been pointed at by the owner and contained no ORBIT data); subsequent runs used non-destructive `migrate` / `db:seed` only.

## Defects found by the test suite and fixed
1. `SubstituteBindings` ran before `SetTenantContext`, so UUID route binding was not tenant-scoped (policy still blocked access â†’ 403 instead of 404). Fixed with `prependToPriorityList(SubstituteBindings::class, SetTenantContext::class)`; test now asserts 404 for a foreign invitation.
2. `EnsureAccountIsOperational` used the cached `organization` relation; a suspension mid-session was not detected for long-lived user instances. Now re-reads the organisation per request.
3. `AcceptInvitation` mass-assigned `email_verified_at` (not fillable by design). Now `forceFill`ed explicitly.
4. `App\Models\Activity` redeclared `$guarded` with a narrower visibility (fatal). Removed.
5. Test bootstrap depended on `DatabaseSeeder`, which the owner extended with `CountryTableSeeder`; tests now seed only roles/permissions, and `SeedingTest` covers the full seeder.

## Unresolved risks / decisions for the owner
- **Namespace layout**: owner-added code references `App\Modules\Shared\Domain\Models\â€¦`; current code is in idiomatic `app/`. Decide before Stage 3 (see assessment Â§8).
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

## Stage 3 â€” Workspace shell, access control, and structure (in progress)
- [x] Split routes by audience: `/org` tenant workspace, `/admin` platform console, `/app` account-neutral landing + profile
- [x] Fixed left sidebar (does not scroll with the page), expandable Users & access / Audit & security, mobile drawer, labelled coming-soon pages
- [x] Staff register separate from login accounts; org admins manage users, roles, and permissions; super admins see every account
- [x] Platform role/permission CRUD; org admins assign any tenant-scoped role including custom ones
- [x] Audit trail and sign-in history as separate pages (tenant and platform)
- [x] Mail header uses `public/images/brand/orbit-lockup-light.png` with `alt="ORBIT"`
- [x] Hostname tenancy + domain registry + organisation branding (ADR-0011). Spatie/stancl not installed; native resolver on shared DB.
- [x] Sortable table headers and visible â€œShowing xâ€“y of zâ€ counts on large lists
- [x] `student` role limited to educational institutions; super-admin product-module placeholders restored
- [x] Tenancy review pass (2026-10-31): idempotent workspace backfill seeder (not a migration), branding/trusted-host cache eviction, DNS label limit, draft vs published assets + authorised preview route, request-local origins for CSRF, host check before `Auth::attempt`, WCAG 4.5:1 ink, custom tenant roles in invitations, preview merge of nulls/assets, factory `updateOrCreate` reuse, colour-picker name, branding CSS after Vite, locked theme toggle, login panel image, central terms/privacy/guide links
- [x] Attendance, QR sessions, field, schedules, leave, notifications, and analytics â€” live tenant screens (2026-11-04). Face, fingerprint, NFC, payroll, and fraud scoring stay out.

## Commands executed and observed results (2026-10-31 review pass)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | 1 unused import removed in `HostnameRoutingTest`; clean afterwards |
| `php84 artisan test --compact` | **175 tests, 175 passed, 1,306 assertions** |

## Recommended next task
Stage 4 â€” Attendance module (QR sessions and field check-in) using the seeded EPA demo locations and departments. Do not implement billing, payroll, BLE, biometrics, or live EPA integrations.

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
| Signed-in HTTP check against `php artisan serve` on `127.0.0.1:8000` | Super admin sees four tabs (Africa's Talking, Arkesel, Twilio, Wigal Frog). Saving a sender ID round-trips and was reverted. Secret inputs stay empty. Health on a disabled provider returns â€œTurn the provider on before checking it.â€ `platform.support` receives 403. Unauthenticated `/admin/sms-providers` redirects to login |
| `php84 artisan test --compact` on SMS, overview, provider settings, and login | **54 tests, 54 passed, 269 assertions** |
| `php84 artisan migrate --force` | Applied `2026_11_02_140000_widen_sms_sender_and_index_messages` |

## Workforce modules (2026-11-04)
- [x] One attendance engine. QR, GPS, and BLE can confirm a check-in. Wi-Fi can only support another method. Face, fingerprint, and NFC stay refused. Payroll and fraud scoring have no tables.
- [x] Tenant routes replace the coming-soon pages. Platform `/admin/workforce` summarises organisations.
- [x] `WorkforceScenarioSeeder` copies fictional demo directories into departments, locations, staff, and a policy. It also backfills notification channels, a beacon and fictional Wi-Fi address per site, day/night or lesson patterns, one field assignment, and one pending leave request. It does not insert attendance counts.
- [x] Workforce writes validate through `App\Http\Requests\Workforce`. Missing check-out, tomorrow's schedule reminder, and quiet field activities run from scheduled commands.
- [x] Local browser check on `https://epa.orbit.test`: records, QR session, field, schedules, leave, notifications, analytics, and My attendance render. A QR check-in recorded Late at 11:04 and check-out closed it. Light and deep-teal themes both load. At 390px the sidebar sits off-canvas and the page does not scroll sideways.

## Commands executed and observed results (2026-11-04 workforce)
| Command | Result |
| --- | --- |
| `php84 artisan test --filter="AttendanceWorkflowTest\|WorkspaceShellTest\|PlatformConsoleTest\|StaffRegisterTest"` | **25 tests, 25 passed, 291 assertions** |
| `php84 artisan test --filter=DemoSeederTest` | **3 tests, 3 passed, 134 assertions** |
| `php84 artisan migrate --force` | Applied the 17 `2026_11_04_100*` workforce tables |
| `php84 artisan db:seed --class=WorkforceScenarioSeeder --force` | Done in 352 ms |
| `php84 artisan test --compact tests/Feature/Workforce/AttendanceWorkflowTest.php` | **8 tests, 8 passed, 112 assertions** (7.2 s) |
| `php84 artisan test --compact` on `AttendanceWorkflowTest`, `DemoSeederTest`, and `WorkspaceShellTest` before the Wi-Fi route-name fix | **14 passed, 1 error** (the error was `org.attendance.wifi` instead of `org.attendance.wifi.store`). The workflow file was re-run after the fix and passed |

## Workspace layout (2026-11-05)
- [x] Audit trail details open in a modal from a view control. The row no longer expands.
- [x] Organisation dashboard replaces the setup checklist with the next five assignments (earliest date first) and leave awaiting a decision.
- [x] Analytics uses today tiles, a daily column chart, and work-type and department bars. Counts stay in SQL. The chart covers at most 62 days.
- [x] QR sessions use summary counts, filters, and paged tables for sessions, beacons, and Wi-Fi. A long history stays one page of 25.
- [ ] `2026_11_05_100000_index_attendance_sessions_by_start` is in the repo and was not applied to the dev database in this pass.

## Commands executed and observed results (2026-11-05 layout)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact --filter="StaffRegisterTest\|WorkspaceShellTest\|AttendanceWorkflowTest\|PlatformConsoleTest"` | **28 tests, 28 passed, 343 assertions** |
| Browser on `https://orbit.test/admin/audit` and `https://epa.orbit.test/org` | Modal opens on the audit eye control. Dashboard shows upcoming assignments and pending leave. Analytics draws one column per day. Sessions filter to an empty page while the totals stay counted. |

## Organisation edits, session spacing, and analytics charts (2026-11-05)
- [x] Department and location pencil controls open `/edit` pages. Create is a page too. The list dialog and the edit page both post a real delete, and the policy still decides who may delete.
- [x] A department or location that still has staff stays in the register. The edit page explains that, and the server refuses the delete.
- [x] QR sessions separate the heading, totals, search, session table, and beacon/Wi-Fi blocks by 2rem.
- [x] Analytics adds a status pie, a status bar for every stored status, and a verification-method bar. Counts stay in SQL. Face, fingerprint, and NFC stay off the chart.

## Commands executed and observed results (2026-11-05 edits and charts)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact` on `StructureTest` and `AttendanceWorkflowTest` | **13 passed, 1 failed** (the failure was an assertion that treated the update URL as the delete URL). Re-ran `department_and_location_edits_are_pages_with_a_permissioned_delete`: **1 passed, 24 assertions** |
| Browser on `https://epa.orbit.test` | Department and location edit pages open from the pencil and keep the saved values. QR session blocks are about 36px apart. Analytics shows an amber Late pie, a Late bar, and a method bar. Delete was not submitted against the demo register. |

## Invitation resend and session check-in (2026-11-05)
- [x] An organisation administrator can resend a pending or expired invitation. The new link replaces the previous one, the expiry restarts, and the action is audited. Accepted, revoked, and another tenant's invitations cannot be resent.
- [x] The session code fills a centred 18rem square, so a phone camera can see the whole code.
- [x] The code encodes a short-lived check-in address. A signed-in person opens it and taps once. The raw code can still be pasted.
- [x] Opening a session notifies each active account that can record attendance with the name, place, time, and a link to My attendance at `#opened-sessions`. The rotating code is not included. Signed-in staff see a live code there and can check in on that device. A new code expires only the code that screen was showing. Bluetooth check-in is a choice of a registered beacon, because the browser cannot hear one. Wi-Fi is explained as supporting evidence only, because the browser cannot read the network address.
- [x] `opening_a_session_tells_people_who_can_check_in_and_the_code_stays_scannable` and `gps_ble_and_wifi_follow_the_verification_rules` passed after the check-in page change. The session mail HTML contains `/org/attendance/mine#opened-sessions`.

## Commands executed and observed results (2026-11-05 resend and check-in)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact` on `InvitationTest` and `AttendanceWorkflowTest` | **24 passed, 1 failed** (the resend test visited the old link while still signed in, so the guest page redirected). Signed out before that visit and re-ran `administrator_can_resend_a_pending_or_expired_invitation`: **1 passed, 15 assertions**. After the session-page copy change, re-ran `opening_a_session_tells_people_who_can_check_in_and_the_code_stays_scannable`: **1 passed, 27 assertions** |
| Browser on `https://epa.orbit.test` | The Test Session code sits in a centred white square and does not cover the page. User accounts shows Resend beside the open invitation. My attendance lists the open session and pre-selects QR. The demo session end time was restored to 21:12 UTC after the check. Resend was not submitted. |

## Leave requests and role wording (2026-11-06)
- [x] Staff request leave from a modal: organisation leave type, start, end, a read-only calendar-day count, description, and an optional document. A type that uses a balance can be requested only while remaining days are above zero.
- [x] The leave page separates pending, due (approved and not yet ended), and approved history. View opens a details modal with status, approver, and note. Approvers get a separate queue.
- [x] Profile and organisation role text use school wording (lecturer) only for educational institutions. A public-agency member sees field officer, not lecturer or student.

## Commands executed and observed results (2026-11-06 leave and roles)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed after fixing import order in `UserAccessTest` |
| `php84 artisan test --compact` on the profile role test, the zero-balance leave test, the overlapping leave test, and the existing profile test | **3 passed, 1 failed** (the failed request had already stored a zero balance, so raising the type default did not restore days). Cleared that balance and re-ran `a_leave_type_with_no_remaining_days_cannot_be_requested`: **1 passed, 12 assertions** |
| Browser on `https://epa.orbit.test` as the EPA organisation administrator | Leave page shows balances, Pending / Due / Approved, and an empty approval queue. View opens the details modal (Submitted, 2 days, note). Request leave calculates 10â€“12 Dec 2026 as 3 read-only days. Profile shows the administrator role with no lecturer or student wording. Roles lists Member as â€œEmployee, staff member, or field officerâ€¦â€ |

## Leave type management and notification ids (2026-11-06)
- [x] Approvers see every leave type on the leave page after it is saved, and can edit the name, code, pay, balance, default days, and whether staff can still request it.
- [x] Database notifications keep a numeric `id`. Laravel's channel UUID is stored in `uuid`. Databases that still had the old UUID primary key are rebuilt by `2026_11_06_090000_align_notifications_table`, and existing notification ids are kept. Every account uses `ReceivesNotifications`, so a database-channel insert cannot write the UUID into `id`.

## Commands executed and observed results (2026-11-06 leave types and notifications)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact` on the notification schema tests and the leave-type edit test | **3 passed, 25 assertions** |
| `php84 artisan migrate --force` | Applied `2026_11_06_090000_align_notifications_table` |
| Live `notifications` columns on `orbit_gh` | `id` is `int8`. Existing rows kept their UUIDs in `uuid` (`id` 1 and 2). A model insert stored numeric `id` 3 with the channel UUID, then that probe row was deleted |
| Browser on `https://epa.orbit.test/org/leave` | Annual, Maternity, and Sick are listed with edit links. Edit Maternity opens name MT, 35 default days, paid, balance, and active. Saving returns to the list with â€œLeave type updated.â€ |

## Staff session check-in (2026-11-06)
- [x] My attendance shows each open sessionâ€™s live code in a centred square. The session email links to `/org/attendance/mine#opened-sessions` and does not contain the code. Staff can scan that square or check in on the same device. A registered beacon is a button. Wi-Fi is listed as supporting evidence only.

## Commands executed and observed results (2026-11-06 staff check-in)
| Command | Result |
| --- | --- |
| `php84 artisan test --filter=opening_a_session_tells_people_who_can_check_in_and_the_code_stays_scannable` | **1 passed, 37 assertions** |
| Browser on `https://epa.orbit.test/org/attendance/mine#opened-sessions` as the EPA organisation administrator | Test QR code session shows a centred white square, a countdown, and â€œCheck in with this codeâ€. Check-in was not submitted. |

## Attendance alignment (2026-11-06)
- [x] A current QR code stays valid for every eligible person until it expires or the screen that showed it rotates it. The same person cannot check in twice for that session. Expired, rotated, and unknown codes are rejected. Only the hash is stored.
- [x] Duplicate check-in is per session or work period. A second session on the same day is a separate record. Leave and an excused day still cover the calendar day. Check-out closes the selected open record.
- [x] GPS acceptance and rejection keep coordinates, accuracy, capture time, location, and geofence result on the attendance event. The raw code is not stored.
- [x] An account with no linked person sees â€œNo person record is linked to this account.â€ Beacon check-in is labelled beacon evidence. Submitting a registered identifier does not claim Bluetooth proximity.
- [x] `2026_11_06_120000_align_attendance_periods_and_shared_qr_tokens` adds `period_key` and `attendance_session_id`, replaces the one-row-per-day unique key, and drops the single-use columns on QR tokens.

## Commands executed and observed results (2026-11-06 attendance alignment)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact tests/Feature/Workforce/AttendanceWorkflowTest.php` | **12 passed, 239 assertions** (165.5 s) |
| `php84 artisan migrate --force` | Applied `2026_11_06_120000_align_attendance_periods_and_shared_qr_tokens` |

## Leave type day count (2026-11-08)
- [x] A new leave type with no day count is stored as 0. An update that omits the field keeps the organisation's configured whole-day count. Half days are rejected. The attendance engine was not changed.

## Commands executed and observed results (2026-11-08 leave type day count)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact tests/Feature/Workforce/AttendanceWorkflowTest.php` | **12 passed, 241 assertions** |

## Review fixes (2026-11-09)
- [x] CSV export prefixes formula-leading text so a spreadsheet does not treat a name or number as a formula.
- [x] An account that can record attendance but has no linked person sees no field assignments or open activities.
- [x] A staff profile includes attendance history only when the caller can view attendance.
- [x] An analytics range longer than 62 inclusive days is not accepted as a chart range.
- [x] Shift times must be a real clock time. Field finish stores the GPS decision, the end location, and checks out the field attendance record.
- [x] Leave decisions lock the request and recheck the remaining balance before approval.
- [x] A check-in during a prior day's overnight shift uses that shift. Session times are stored in UTC. Trend counts attendance records.
- [x] A future session display keeps asking for its first code. Field assignments, upcoming shifts, and notification channels can all be reached or turned off.

## Commands executed and observed results (2026-11-09 review fixes)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact tests/Feature/Workforce/AttendanceWorkflowTest.php` | **14 passed, 309 assertions** |
| `php84 artisan migrate --force` | Applied `2026_11_09_070000_link_field_activities_to_attendance_records` |

## Schedule and leave policy (2026-11-09)
- [x] A person can hold more than one non-overlapping work period on a date. Each period stores a work arrangement and an expected location.
- [x] Work patterns and a non-working calendar generate assignments. The attendance engine reads the period; it does not grow a second decision path.
- [x] Leave entitlement is a tenant policy and ordered rules. An open balance keeps the days it was given. Adjustments are separate audited rows. Pending days are reserved at submit.
- [x] `leave.policy` is separate from `leave.approve`. Self-approval is refused unless the policy allows it.
- [x] Design: ADR-0014 and `schedule-and-leave.md`.

## Commands executed and observed results (2026-11-09 schedule and leave)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact tests/Feature/Workforce/ScheduleLeaveFoundationTest.php tests/Feature/Workforce/AttendanceWorkflowTest.php tests/Feature/Shell/WorkspaceShellTest.php` | **25 passed, 420 assertions** |
| `php84 artisan migrate --force` | Applied `2026_11_09_120000_schedule_leave_policy_foundation` |
| `php84 artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder --force` | Granted `leave.policy` to organisation administrators |

## Schedule and leave validation (2026-11-09)
- [x] Opening Schedules does not insert work arrangements. They are created with the organisation, or by an explicit action.
- [x] Assignment clocks are snapshotted. A past assignment, and one that already has a check-in, cannot be removed. Two non-overlapping periods can each record attendance.
- [x] Entitlement uses priority, then id. A request cannot cross a leave year. An adjustment cannot drop the balance below reserved days. Self-approval follows the policy. Closed requests stay closed.
- [x] Notices are queued after commit. A failed SMS send does not fail the schedule or leave action.
- [x] Shift, pattern, calendar, leave history, and approval lists are paged. Line manager, department head, and field person pickers search instead of loading a fixed headcount.
- [x] Demo directory covers office, remote, hybrid, field, finance, manager, junior, and school teaching, administrative, and support categories. Educational annual leave starts on 1 July when the policy was still the calendar-year default.
- [x] Design updated in ADR-0014 and `schedule-and-leave.md`.

## Commands executed and observed results (2026-11-09 schedule and leave validation)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact` | **256 passed, 2050 assertions** |
| `php84 artisan test --compact tests/Feature/Staff/StructureTest.php tests/Feature/Staff/StaffRegisterTest.php tests/Feature/Workforce/AttendanceWorkflowTest.php tests/Feature/Shell/WorkspaceShellTest.php tests/Feature/Workforce/ScheduleLeaveValidationTest.php` | **43 passed, 597 assertions** after the searchable pickers |
| `php84 artisan migrate --force` | Applied `2026_11_09_180000_schedule_leave_integrity` |
| `php84 artisan db:seed --class=Database\\Seeders\\WorkforceScenarioSeeder --force` | Completed in 783 ms |
| `php84 artisan db:seed --class=Database\\Seeders\\SystemDemoUserSeeder --force` | Completed in 4.6 s. Refreshed the fictional EPA and UPSA directories |

## Schedule and leave review remediation (2026-11-10)
- [x] A leave-year anchor cannot change while that type has a balance or a submitted or approved request. The period end is the day before the next configured anchor.
- [x] Checkout, My attendance, and missing-checkout cleanup include an open overnight period until its scheduled end. Checkout punctuality uses the record's assignment.
- [x] A published assignment's location is authoritative. A conflicting supplied location is rejected.
- [x] Between two periods, early check-in uses `grace_minutes` and does not select a period that has ended.
- [x] Assignment removal and check-in lock the person row. Removal rechecks attendance inside that lock.
- [x] A queued notice restores the tenant that was current before the job. Delivery is claimed per recipient, key, and channel.
- [x] Legacy submitted leave is reserved. The stored entitlement is not rewritten. A shortfall is one audited adjustment. Legacy approved leave cancels against its original calendar year. Unambiguous legacy attendance is linked to its assignment.
- [x] The approval queue follows the policy mode. An authorised approver can open and cancel another person's approved leave.
- [x] Schedule adherence counts the matching assignment. Scheduled arrangements exclude draft shifts.
- [x] The department form keeps the code field beside the department head.
- [x] Design updated in ADR-0014 and `schedule-and-leave.md`.

## Commands executed and observed results (2026-11-10 review remediation)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Passed |
| `php84 artisan test --compact tests/Feature/Workforce/ScheduleLeaveReviewTest.php tests/Feature/Tenancy/HostnameRoutingTest.php tests/Feature/Staff/StructureTest.php tests/Feature/Workforce/ScheduleLeaveFoundationTest.php tests/Feature/Workforce/AttendanceWorkflowTest.php` | **62 passed, 752 assertions** |
| `php84 artisan test --compact` | **276 passed, 2315 assertions**. No failures. No skipped tests |
| `php84 artisan migrate --no-interaction` | Applied `2026_11_10_090000_schedule_leave_legacy_backfill` in 710 ms. That run used the earlier class, which could raise `entitled_days`. The class now records a shortfall as an adjustment and does not rewrite entitlement. This database will not re-run the migration. Its five balances are inside the check and have no legacy adjustment rows |

## Workforce notifications (2026-11-10)
- [x] Extend the existing notifier, queued job, SMS transmitter, and database notifications. No second provider interface, no public API, no push or WhatsApp delivery.
- [x] Tenant communication policy, optional personal preferences, and server-rendered templates. Mandatory events ignore a personal opt-out.
- [x] In-app inbox with unread count, categories, pagination, and safe action links. Dashboard card and header count.
- [x] Leave, schedule, and attendance notices dispatch after commit. A provider failure does not undo the business row. Delivery claims stop a duplicate send.
- [x] Daily reminders for tomorrow's shift, yesterday's missed attendance, leave waiting two days, and balances ending within 14 days.
- [x] Design in ADR-0015, `requirements/10-workforce-notifications.md`, and `implementation/workforce-notifications.md`.

## Commands executed and observed results (2026-11-10 workforce notifications)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact tests/Feature/Notifications/WorkforceCommunicationTest.php tests/Feature/Notifications/DatabaseNotificationSchemaTest.php` | **8 passed, 112 assertions** |
| `php84 artisan test --compact tests/Feature/Workforce/AttendanceWorkflowTest.php tests/Feature/Workforce/ScheduleLeaveReviewTest.php tests/Feature/Shell/WorkspaceShellTest.php tests/Feature/Staff/StaffRegisterTest.php tests/Feature/Tenancy/HostnameRoutingTest.php` | **60 passed, 748 assertions** |
| `php84 vendor/bin/pint --dirty` | Fixed `WorkforceNotifier.php` and `WorkforceCommunicationTest.php`, then a second run **passed** |
| `php84 artisan test --compact` | **282 passed, 2418 assertions**. No failures. No skipped tests |

## Notification read audit and bell (2026-11-10)
- [x] One migration per communication table (`2026_11_10_140100` through `140600`), including `notification_read_audits`.
- [x] Inbox ownership stays on `User`. Read state is `read_at`. The first unread-to-read transition writes one audit row. Mark-all audits only those transitions.
- [x] Header bell lists five recent notices, marks the opened notice read, and follows a safe action URL. View all opens the paginated centre.
- [x] ADR-0015, `requirements/10-workforce-notifications.md`, and `implementation/workforce-notifications.md` describe ownership, read audit, bell, delivery versus read state, tenant scope, at-least-once mail, and the production queue worker.

## Commands executed and observed results (2026-11-10 notification gate)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact` on the notification feature tests | **12 passed, 166 assertions** |
| `php84 artisan test --compact tests/Feature/Workforce/ScheduleLeaveReviewTest.php --filter=the_approval_queue_follows_the_policy_approval_mode` | **1 passed, 20 assertions** |
| `php84 vendor/bin/pint --dirty` | Fixed import order in `ScheduleLeaveReviewTest.php`, then a second run **passed** |
| `php84 artisan test --compact` | **286 passed, 2472 assertions**. No failures. No skipped tests |
| `php84 artisan migrate` | Applied `140100` through `140600` on the local database |

## Database operation split (2026-11-10)
- [x] `2026_11_09_120000_schedule_leave_policy_foundation` is replaced by one schema file per table, `120100` through `121300`.
- [x] Existing-data repair is `orbit:data:backfill-leave-periods`, `backfill-leave-requests`, `backfill-workforce-v1`, `backfill-shift-clocks`, `backfill-notice-claims`, `backfill-notification-inbox`, and `backfill-notification-reads`.
- [x] `2026_11_10_140400` adds nullable delivery status and does not mark claims sent. A null claim is not resent. `orbit:data:backfill-notice-claims` records it as sent.
- [x] `2026_11_10_140500` adds nullable inbox organisation and event and does not copy existing rows. `orbit:data:backfill-notification-inbox` fills what it can. The columns stay nullable.
- [x] `2026_11_10_090000_create_workforce_notice_deliveries` is the structural name. The historical local filename was `2026_11_10_090000_schedule_leave_legacy_backfill`. It was not released to production.
- [x] Guideline in `architecture/database-operations.md`.

## Commands executed and observed results (2026-11-10 database operations)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact tests/Feature/Database/DataUpgradeTest.php --filter=workforce_backfill_links_a_legacy_request_once` | **1 passed, 9 assertions**. The first full file run had failed this test on the expected update count |
| `php84 vendor/bin/pint --dirty` | Fixed an end-of-file line in `BackfillNotificationReads.php`, then a second run **passed** |
| `php84 artisan test --compact` | **292 passed, 2519 assertions**. No failures. No skipped tests |
| `php84 artisan migrate --force` | Recorded `120100` through `121300`. Existing tables were left in place |

## Analytics, dashboards, and insights (2026-11-11)

- [x] `AnalyticsScope` limits organisation, department-head, and personal aggregates. A filter can narrow that scope and cannot widen it. ADR-0016 and `implementation/analytics-and-dashboards.md`.
- [x] `WorkforceReport` remains the attendance and schedule-adherence source. `OperationalDigest` adds leave-year utilisation and separates notification delivery from read state.
- [x] Organisation, department, and personal dashboards share `DashboardComposer`. The platform dashboard charts registration weeks and does not open tenant attendance.
- [x] Insights are deterministic and cite the metric. No external model is bound. `OperationalInsightProvider` is the later adapter.
- [x] Charts stay server-rendered with `ChartPalette`. Authenticated pages do not plot the labelled sample metrics block.
- [x] A department head who is not an organisation administrator is limited to headed departments on the dashboard, analytics, CSV, attendance, staff register (including view and update), schedule lists and mutations, and the leave approval queue. An organisation manager who heads no department stays organisation-wide. `organization.update` stays organisation-wide.
- [x] The attendance CSV uses the same scope. PDF, Excel, and scheduled delivery stay on the next milestone.
- [x] The organisation/read index is created by `2026_11_11_080000_index_notifications_by_organization_read` and also by the up path of `140500`, so a fresh database and an already-migrated database both get it. Rolling back `140500` drops that index before `organization_id`.

## Commands executed and observed results (2026-11-11)
| Command | Result |
| --- | --- |
| `php84 artisan test --compact` | First full run **failed**: 311 passed, 1 error. `DatabaseNotificationSchemaTest::inbox_column_rollback_drops_organization_id_with_or_without_its_foreign_key` could not drop `organization_id` while `notifications_organization_read` still existed |
| `php84 artisan test --compact tests/Feature/Notifications/DatabaseNotificationSchemaTest.php --filter=inbox_column_rollback_drops_organization_id_with_or_without_its_foreign_key` | **1 passed, 12 assertions**, after the rollback drops the index first |
| `php84 vendor/bin/pint --dirty` | **Passed** |
| `php84 artisan test --compact` | **312 passed, 2689 assertions**. No failures. No skipped tests |
| `php84 artisan migrate --force` | Applied `2026_11_11_080000_index_notifications_by_organization_read` to the development PostgreSQL database. `notifications_organization_read` is present. No rollback was run |

## Dashboard and UI hardening (2026-11-11)

- [x] The organisation dashboard no longer shows the calculated-note list. Attention is the uncovered, schedule, and leave figures, with drill-down links. Calculated notes stay on analytics, grouped as action required now and planning attention.
- [x] Present today is labelled as a stored status. Uncovered today is labelled as derived when no location filter is set.
- [x] Personal leave figures on the dashboard use the same available-day figure as the leave page.
- [x] Chart date labels use day/month and scroll inside the card. Screen-reader tables no longer widen the page.

## Commands executed and observed results (2026-11-11 hardening)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Fixed `DashboardComposer.php`, then a second run was clean |
| `php84 artisan test --compact` | **314 passed, 2708 assertions**. No failures. No skipped tests |

## Visual system (2026-11-11)

- [x] Authenticated pages no longer use a floating card as the default container. Inset strips, open sections, and hairline panels are the grammar in `.cursor/docs/design/visual-system.md`.
- [x] The organisation dashboard reads: attention, one position strip, a conclusion then the chart, one secondary column, then an administration link row.
- [x] The sidebar shows the ORBIT mark and the workspace name once. The top bar shows the date on desktop and the workspace name on small screens.

## Commands executed and observed results (2026-11-11 visual system)
| Command | Result |
| --- | --- |
| `php84 artisan view:cache` | Blade templates cached successfully |
| `npm run build` | Built. `public/build/assets/app-D-3X6tRj.css` |
| `php84 artisan test --compact` | **314 passed, 2708 assertions**. This run was before the final sidebar wordmark |
| `php84 artisan test --compact tests/Feature/Shell/WorkspaceShellTest.php tests/Feature/Analytics/DashboardAnalyticsTest.php tests/Feature/Staff/StaffRegisterTest.php` | **26 passed, 199 assertions**, after the sidebar wordmark |
| `php84 vendor/bin/pint --dirty` | **Passed** |

## Visual quality recovery (Milestone 5 refinement, 2026-11-11)

The earlier flat pass was treated as a regression: equal-weight grey insets, unbounded text sections, and sparse charts. The visual system was revised (see `.cursor/docs/design/visual-system.md`). No backend, formula, policy, scope, or query change.

- [x] Design system: resting panel shadow, `rounded-xl` panels, `.briefing`, `.toolbar`, `.table-panel`, severity rails, icon wells, meters, plot gridlines.
- [x] `x-stat` KPI tile with icon well, semantic emphasis edge, optional meter, hover affordance. `x-card` with title icon and an "Informs" decision footer.
- [x] Charts: `donut` (replaces the solid pie; `pie` delegates), `bars`, `gauge`, refined `stacked` and `columns`. Every chart keeps a screen-reader table; no-data is a sentence.
- [x] Organisation and department dashboard: operational briefing, today's position, trend + today's coverage, schedule / leave / composition, notifications + administration.
- [x] Personal workspace: today briefing with next shift, three KPI tiles, recent attendance, balances, requests, notices.
- [x] Analytics: operational intelligence (calculated notes, still labelled Calculated), today tiles, trend with summary, status share with counts, methods / work type / department, adherence gauge, leave decisions, arrangements, notifications.
- [x] Platform console: platform briefing with signals or health checks, five KPI tiles, registrations, tenant-health donut, SMS delivery by day, security, recent organisations and audit.
- [x] Shell: workspace card with monogram, rounded active navigation, 17rem sidebar.
- [x] Public home: illustrative workspace preview, audience band, problem â†’ outcome, capabilities with the question each answers, four-step process, safeguards, CTA.
- [x] Operational pages: attendance tiles + toolbar, leave balance meters and status panels (dates now `j M Y`), notifications inbox panel, profile panels, tables as one panel.

## Commands executed and observed results (2026-11-11 visual recovery)
| Command | Result |
| --- | --- |
| `npm run build` | Built |
| `php84 artisan test --compact` | **314 passed, 2708 assertions**. No failures |
| Browser (Edge, Playwright) | Ama, Esi, Kojo, platform admin, public pages at 390 / 768 / 1280; light and deep teal; no page-level horizontal overflow |

## Platform statistics, workforce overview, school tenant, and public help (2026-11-12)

- [x] `/admin/statistics` (`admin.statistics`, sidebar *Statistics*, `platform.access`): `App\Analytics\PlatformStatistics` composes organisation status and type, account health, sign-ins over 30 days, register composition, invitation outcomes, 12-month growth, 14-day sign-in outcomes, and the largest organisations by register size. Aggregates only; no tenant attendance, leave, schedules, or member names.
- [x] `/admin/workforce` redesigned: briefing (organisations reporting today, active people covered), six KPI tiles, organisation search, and a register with active people, a covered-today bar, present Â· late, open field work, and pending leave. Today is each organisation's local date; counts are distinct people. Covered as before by `platform.access`.
- [x] School tenant (UPSA) reviewed as administrator, manager (Student Affairs scope), and student at 390 and 1280. Dashboard tile reads *Active people* with "staff and students" for educational organisations. Monograms skip filler words (`App\Support\Monogram`): "UP", not "UO".
- [x] Password fields (`x-form.input type="password"`) have a show/hide eye button: stays `type="password"` until pressed, `aria-pressed`, `aria-controls`, and a "Show â€¦" / "Hide â€¦" label.
- [x] Public user guide rewritten against the current product: roles, departments and locations, staff register, invitations and suspension, check-in methods and lateness, QR sessions and corrections, schedules, leave, notifications, dashboards, analytics and CSV, audit, schools and universities, account and theme, and what is not in this release.
- [x] Contact page FAQ (`#faq`): four topic groups of native `<details>` disclosures below the form and demo block.

| Command | Result |
| --- | --- |
| `php84 artisan test --compact tests/Feature/Public tests/Feature/Platform tests/Feature/Analytics tests/Feature/Shell` | **70 passed, 493 assertions** |
| `php84 artisan test --compact` | **321 passed, 2751 assertions**. No failures |
| `php84 vendor/bin/pint --dirty` | **Passed** |
| Browser | Password toggle switches `password` â†’ `text` â†’ `password`; FAQ item opens and closes; `/admin/statistics`, `/admin/workforce`, `/user-guide`, `/contact`, UPSA dashboard at 390 and 1280 with no page overflow |

## Final database-operation review (2026-11-10)
- [x] `140400` no longer marks claims sent. Null status is not resent. `orbit:data:backfill-notice-claims` records it as sent.
- [x] `140500` no longer copies inbox rows. `orbit:data:backfill-notification-inbox` fills a missing organisation and event. The columns stay nullable.
- [x] `2026_11_10_090000_create_workforce_notice_deliveries` is the file on disk. The historical local name is documented. It was not released to production.
- [x] Migration-history rollback limits are in `architecture/database-operations.md`.

## Commands executed and observed results (2026-11-10 final review)
| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | **Passed** |
| `php84 artisan test --compact tests/Feature/Database/DataUpgradeTest.php` | **8 passed, 75 assertions** |
| `php84 artisan test --compact` | **294 passed, 2547 assertions**. No failures. No skipped tests |

## Review fixes (2026-11-12)

- [x] Attendance correction, assignment removal, and staff create/update reject a department outside the acting manager's scope before the write. Assignment removal checks the shift department and the assigned person's department.
- [x] Leave and schedule windows include the final day with a half-open calendar bound. An open leave balance includes its first day.
- [x] Platform coverage counts each active person once across covering statuses. Bar segments use that same non-overlapping split.
- [x] The sidebar workspace name keeps a published branding display name.
- [x] The personal dashboard counts submitted leave separately from the five recent requests, and shows "No day limit" for unlimited types.

| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | **Passed** (import order in two tests) |
| `php84 artisan test --compact` on the new regression methods | **8 passed, 41 assertions** |
| `php84 artisan test --compact` on `DashboardAnalyticsTest`, `PlatformStatisticsTest`, `StaffRegisterTest`, `WorkspaceShellTest`, `ScheduleLeaveReviewTest`, and `AttendanceWorkflowTest` | **71 passed, 826 assertions** |
| `php84 vendor/bin/pint --dirty` then `php84 artisan test --compact` (full suite, 2026-11-12 validation) | Pint **passed**. **329 passed, 2792 assertions**. No failures |

## Milestone 6 — Reports & exports (2026-11-12)

- [x] ADR-0017 and `implementation/reports-and-exports.md`: reusable `App\Reports` contract on top of `AnalyticsScope` / `WorkforceReport` / `OperationalDigest`.
- [x] Tenant catalog at `/org/reports` and platform catalog at `/admin/reports`.
- [x] First-pass reports: attendance summary / by person / by department / detail, punctuality, incomplete, verification methods, schedule coverage, leave summary, notification delivery (org perspective), platform organisation statistics.
- [x] Shared exporters: CSV stream, OpenSpout XLSX, Dompdf PDF (summary reports). Queued path for large XLSX/PDF (`report_exports`).
- [x] `org.analytics.export` remains a thin alias of attendance-detail CSV.
- [x] Authorization, tenant isolation, department narrowing, forged filters, export parity, date bounds, and queue coverage in `tests/Feature/Reports/ReportsAndExportsTest.php`.

| Command | Result |
| --- | --- |
| `php84 vendor/bin/pint --dirty` | Fixed import/style in report controllers, ExportManager, routes, and reports tests; second run clean |
| `php84 artisan test --compact` | **345 passed, 2,854 assertions**. No failures |

