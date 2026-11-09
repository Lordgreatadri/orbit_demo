# Workforce modules — assessment and implementation sequence

- Status: Implemented for the reserved workforce screens. Face, fingerprint, NFC, payroll, and fraud scoring remain plugs or out of scope.
- Date: 2026-11-04
- Sources: `.cursor/docs/requirements/`, `.cursor/docs/architecture/`, `.cursor/rules/`, and the Laravel application as it exists in the repository.

The Word documents named in the implementation brief match the Markdown specification already in `.cursor/docs/`. Where the brief and the accepted product scope disagree, this assessment follows the accepted scope and records the disagreement. It does not drop a requirement.

## What is working

- Shared-database tenancy. The tenant comes from the hostname through `organization_domains`. `IdentifyTenant` runs before route-model binding. `OrganizationScope` constrains tenant-owned models.
- Organisation branding and workspace settings, including the light theme and the deep-teal / midnight-plum alternate.
- Session authentication, invitation-only membership, platform and tenant roles, and the audit log (`activity_log.organization_id`).
- Staff directory, departments, and locations. A location may already store latitude, longitude, and a geofence radius from 10 to 5,000 metres, with type `office`, `field_area`, or `remote`.
- Platform SMS behind `SmsTransmitter`, queued delivery, and Laravel database notifications with a numeric primary key plus a UUID. Phone confirmation still uses the log sender.

## What stays reserved

The reserved screens are live. Face, fingerprint, NFC, payroll, and fraud scoring stay named plugs: the engine refuses the three verification names, and there is no capture, payroll table, or scoring job.

## What this milestone implements

- One attendance engine with check-in, check-out, statuses, grace and working hours, missing check-out, duplicate prevention, authorised corrections, and an attendance event trail.
- Dynamic QR sessions with hashed, short-lived tokens. A current code is shared by the people at that session. It is not consumed by the first person.
- Server-side GPS and geofence decisions. Browser coordinates are evidence, not proof.
- BLE as a confirming method when a registered beacon identifier is submitted. Wi-Fi can only support another method.
- Field assignments, start and finish with a location reading, delayed state from the last report, and a map link. No background tracking.
- Shifts and lessons, publication, conflict detection, and midnight shifts in the organisation timezone.
- Leave types, requests, approval, balances when enabled, and approved leave on attendance.
- Workforce messages on mail, the database inbox, and platform SMS. Push is an unbound plug.
- Daily and ranged reports counted in SQL, with a permitted CSV export. PDF is not bundled.

Request validation for these screens lives in `App\Http\Requests\Workforce`. `WorkforceScenarioSeeder` backfills channels, beacons, fictional Wi-Fi addresses, shift patterns, one field assignment, and one pending leave request. It does not insert attendance counts.

## Conflict to hold

`.cursor/docs/requirements/00-product-scope.md`, the EPA demo requirements, and `phase-1-plan.md` keep BLE, Wi-Fi attendance, NFC, facial recognition, fingerprint biometrics, payroll, government integrations, subscriptions, and AI fraud outside the current prototype. The implementation brief asks for those methods and for AI fraud in the same programme.

Resolution recorded in ADR-0013: QR, GPS, and registered beacon evidence can confirm attendance. The beacon identifier does not prove proximity. Wi-Fi and device binding can only support another method. One-time codes stay off until the organisation enables them. Face, fingerprint, NFC, payroll, and fraud scoring stay refused. QR and GPS reduce risk; neither is treated as fraud-proof. The EPA tenant remains one fictional demo, not the shape of the rules.

## Smaller reconciliations

- QR lifetime is configurable per session. The EPA fictional seed can use 45 seconds. The architecture note of 10–30 seconds is the default range, not a hardcoded tenant rule.
- The architecture overview still mentions Laravel 12. ADR-0002 and `composer.json` record Laravel 13. The installed application is the source of truth.
- `09-acceptance-criteria.md` still has open boxes for foundation work that the progress log and the test suite already cover. That is documentation drift.
- A user belongs to one organisation through `users.organization_id`. A membership table is not required for this milestone.
- `staff_members` is the workforce person, including someone who will record attendance. The `student` role does not have a second person table. A user who records attendance needs a person row in that directory.
- Laravel's `notifications` table is reused. Workforce messages do not get a second notification table.
- `activity_log` remains the cross-cutting audit. Attendance also gets its own event rows so a decision can store method, outcome, and evidence without putting a raw QR token in the audit log.

## Schema in place, one table per migration

No seed rows in migrations. Demo backfill is in `WorkforceScenarioSeeder`.

1. `workforce_policies`
2. `attendance_records`, `attendance_events`
3. `attendance_sessions`, `attendance_tokens` (token hash only)
4. `beacons`, `wifi_networks`, `bound_devices`, `attendance_otps`
5. `field_assignments`, `field_activities`
6. `shifts`, `shift_assignments`
7. `leave_types`, `leave_requests`, `leave_balances`
8. `notification_preferences` (delivery flags only; messages use Laravel's `notifications` table)
9. No report fact table; reports query the records above

## Security constraints that already apply

- Tenant id comes from the authenticated organisation, never from the client body.
- Policies use the reserved permissions.
- Critical writes run in a transaction.
- QR tokens are random, stored as hashes, and short-lived. A current code works for every eligible person until it expires. Replay is refused per person and session. Personal one-time codes stay single-use.
- Location capture happens at an approved attendance or field event, with a retention limit.
- Queued jobs carry the organisation id and restore tenant context.
- Rate limits stay on session creation, attendance submission, and exports.

## Sequence

1. Attendance engine
2. Dynamic QR
3. GPS and geofencing
4. Field workforce monitoring
5. Shift scheduling
6. Leave management
7. Workforce notifications
8. Reporting and the record-backed dashboard

Each module is finished, tested, and documented before the next one starts.
