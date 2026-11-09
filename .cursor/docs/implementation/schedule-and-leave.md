# Schedule and leave

Accepted design: ADR-0014. The attendance engine is unchanged as the check-in decision. Schedule resolves an expected work period. Leave supplies the counted absence dates, and the engine projects them.

## Schedule

- `work_arrangements` — tenant name plus a kind (`office`, `remote`, `field`, `flexible`). Office, Remote, Field, and Flexible are created when the organisation is provisioned. If they are missing, a manager adds them with the Schedules action. Viewing the page does not insert rows.
- `work_patterns` — weekday template, optional department, category, location, and arrangement. Apply writes at most 62 days and walks active staff in chunks of 100.
- `shift_assignments` — the resolved period. More than one per date when the clocks do not overlap. The assignment stores its own start, end, and midnight flag, copied from the shift at assignment time. A past date, or a period that already has a check-in, stays on record.
- `non_working_days` — one row per organisation date (holiday, closure, or non-working). Pattern apply skips it. Working-day leave skips it when the policy says so. There is no separate person-level exception table.

Check-in looks up the published assignment with `organization_id`, `staff_member_id`, and a half-open range on `work_date` (`>= date` and `< next date`). That uses `shift_assignments_period_unique` without wrapping the column in a date function, and it still matches a midnight timestamp. A check-in outside a QR session uses `period_key` `shift:{assignment id}` and the assignment work date. A session check-in keeps `session:{id}`. Reminders claim `reminded_at` before the notice is queued, so a second run does not queue another reminder.

## Leave

- `leave_policies` — leave year start month and day, calendar or working count, weekend days, approval mode, self-approval. A request whose dates fall in two leave years is refused.
- `leave_entitlement_rules` — `priority` ascending, then `id` ascending. The first match wins. Blank criteria match everyone. Category, department, grade, employment type, and months of service are optional filters. The numbers are tenant data. A future `started_on` is zero months of service, not a long-service match.
- `leave_balances` — unique on person, type, and `period_start`. `pending_days + used_days` cannot exceed `entitled_days + adjusted_days`. The service checks this under the person lock. PostgreSQL and MySQL also store the check constraint `leave_balances_within_entitlement`.
- A request stores `day_count` and `covered_dates` so a later policy edit does not rewrite that request.

Submit, amend, withdraw, approve, reject, and cancel lock the person row, then the request and balance. Overlap of submitted or approved leave is checked again under that lock. Rejected, withdrawn, and cancelled requests cannot be approved. A cancelled request cannot be cancelled again. Self-approval is refused unless that policy sets `allow_self_approval`.

Notices go out after commit through `DeliverWorkforceNotice`. The leave or schedule transaction does not wait on the SMS provider. Mail is the queued `WorkforceMessage`. Push remains the null plug until a provider is registered.

## Queries worth keeping bounded

| Purpose | Pattern | Scale | Index |
| --- | --- | --- | --- |
| Check-in period | One person, one or two dates, published shift. The date test is `work_date >= day AND work_date < next day` | One person per check-in | `(organization_id, staff_member_id, work_date, shift_id)` |
| Upcoming roster | Organisation, `work_date >= today`, 30 rows | Page, not the full history | `(organization_id, work_date)` |
| Shift, pattern, and calendar lists | 15, 10, and 15 rows | Further pages | Organisation and date indexes already on those tables |
| Leave lists | Own pending, due, approved, and closed pages of 15. Approval queue page of 20 | Further pages | `(organization_id, staff_member_id, status, starts_on)` and `(organization_id, status, starts_on)` |
| Department and location choices | Search of at least two characters, 60 rows | Not the whole register | Name lookup inside the tenant |
| Balance | Person, type, and the same half-open test on `period_start` | One row | `leave_balances_period_unique` |
| Adherence | Assignments in a range of at most 62 days, exists attendance | Report range, grouped in SQL | Assignment date index and attendance `(organization_id, staff_member_id, period_key)` |

A date column is compared as a half-open range (`>= day` and `< next day`). That matches a MySQL `DATE` and a SQLite midnight timestamp, and it does not wrap the column in `date()`, so the index remains usable. `App\Support\CalendarDay` is the shared form of that test.

Person pickers search by name and return at most 30 rows. They do not load the whole register. The line-manager field, the department-head field, and the field-assignment person field work the same way. The current selection stays in the list. The dashboard still shows five upcoming assignments and five pending requests as a preview. Analytics leave decisions, schedule adherence, and scheduled arrangements are SQL aggregates for the selected range, capped at 62 days.

## What this does not do

Half-day leave, a generated rotation beyond the pattern apply, and a public API are not in this milestone. Absence on the attendance dashboard is still "active people without a covering attendance row". Schedule adherence on Analytics is the figure that uses the roster.
