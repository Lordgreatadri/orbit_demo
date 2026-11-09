# ADR-0014: Schedule work periods and leave entitlement

- Status: Accepted
- Date: 2026-11-09

## Context

Schedule stored one assignment per person per date, and leave entitlement was `leave_types.default_days` for every person. ORBIT has to serve office, remote, field, and hybrid work, and tenants whose leave depends on category, grade, employment type, or service. Schools use the same workforce core. A school-specific code path is not acceptable.

The attendance engine already decides check-in. Schedule and leave must give it context.

## Decision

- A shift assignment is one expected work period. The unique key is `(organization_id, staff_member_id, work_date, shift_id)`. Overlapping clock times are rejected in the schedule service. Adjacent periods do not overlap: 09:00–12:00 and 12:00–17:00 are kept, and 09:00–13:00 with 12:00–17:00 is refused. A second, non-overlapping shift on the same date is allowed.
- Assigning a period copies `starts_at`, `ends_at`, and `crosses_midnight` onto the assignment. A later edit of the shift template does not rewrite that snapshot, so historical attendance stays explainable. A past assignment, and an assignment that already has a check-in, cannot be removed.
- Each assignment stores the work arrangement and the expected location. Arrangement `kind` is `office`, `remote`, `field`, or `flexible`. The tenant chooses the name. Office, Remote, Field, and Flexible are created when the organisation workspace is provisioned, and again by an explicit action on Schedules. Opening the page does not insert them. Hybrid work is a week of different arrangements, not a separate engine. The person's standing work type does not override a published period.
- A work pattern is a weekday template. Applying it writes assignments and skips the tenant non-working calendar. The assignment remains the historical fact.
- The attendance engine reads the period that contains the check-in. Office and field periods do not use the person's standing remote or hybrid flag to skip a geofence. Remote and flexible periods may, when the organisation policy allows check-in without a geofence. A check-in that is not part of a QR session is stored as `period_key` `shift:{assignment id}` on the assignment's work date, so two periods on one date can each have a record. A session check-in keeps the session key.
- Leave approval still covers the counted dates for that request. The engine writes and clears `source = leave` attendance rows only when that row has no check-in. Leave does not decide present or late. A request that crosses a leave year is refused; the person submits one request for each year.
- A leave policy sets the leave-year start, whether days are calendar or working days, the weekend, and who may approve. Entitlement rules are ordered by `priority` ascending, then `id` ascending. The first rule whose filled criteria match wins. A blank criterion matches everyone. Months of service are measured from `started_on` to the period start, and a future start date counts as zero months. Nothing matches falls back to `leave_types.default_days`. A catch-all rule must use a higher priority number than a specific rule. ORBIT does not compute specificity. Later rule edits do not change an open balance. An authorised adjustment is a separate audited row and cannot take `pending_days + used_days` above `entitled_days + adjusted_days`.
- Pending days are reserved at submit. Approval moves them to used inside a transaction that locks the person, rechecks overlap, and refuses self-approval unless the policy allows it. PostgreSQL and MySQL also enforce that balance inequality with `leave_balances_within_entitlement`. SQLite cannot add a check constraint with `ALTER TABLE`, so the test suite proves the service check on one connection. Two PHPUnit processes cannot share the in-memory database, so a true parallel lock is not what that test executes.
- `leave.policy` is separate from `leave.approve`. After the self-approval check, a `leave.policy` holder may decide a request even when the approval mode names a line manager or department head. When that relationship is unset, any `leave.approve` user may decide.
- Schedule and leave notices are dispatched after the database commit on `DeliverWorkforceNotice`, with a stable dedupe key. Mail, the optional push plug, and the SMS row are written in that job. A failed SMS send is reported and does not fail the leave or schedule action. The provider HTTP call remains `DeliverSmsMessages`. Switching among registered SMS providers is configuration. A new vendor still needs an adapter.

## Consequences

- Rosters are generated from patterns in stretches of at most 62 days, not inferred on every check-in. Pattern apply walks staff in chunks and does not stop at a fixed headcount.
- Half-day leave, a generated rotation beyond pattern apply, a public API, and a person-specific non-working calendar are not part of this decision. A person-specific day off is an unassigned future period, or leave.
- Line-manager and department-head approval apply when those relationships are set. Otherwise an approver with `leave.approve` can decide. A `leave.policy` holder remains an override after self-approval is checked.
- Run `php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder` after deploy so organisation administrators receive `leave.policy`.
