# Analytics, dashboards, and insights

This describes the implementation behind ADR-0016. It is not a promise of a later export product or an external model.

## Perspectives

| Perspective | Who | What they can aggregate |
| --- | --- | --- |
| Organisation | `organization_admin`, or `organization.update`, or `analytics.view` when the person heads no department | The tenant |
| Department | `analytics.view`, not an organisation administrator, and `departments.head_staff_member_id` is their staff row | Those departments only. The same limit applies to the staff register, schedule lists, and the leave approval queue. |
| Personal | No `analytics.view` | Their own staff row. An unlinked account sees an empty personal workspace |
| Platform | Platform console | Organisation counts, registration weeks, sign-in totals, SMS health. Not tenant attendance or leave |

Hiding a widget is not the control. `AnalyticsScope` is applied in the report queries, the attendance register, and the CSV export.

## KPI definitions

Dates are organisation-local calendar dates. Stored timestamps stay UTC. A range is half-open on `work_date` (`>= from` and `< the day after to`) so SQLite and PostgreSQL match. The analytics page accepts at most 62 inclusive days. A longer or reversed range is replaced with the latest 30 days, and the page says so.

### Today's attendance

Source: `attendance_records` and `staff_members`.

- Present, late, on leave, incomplete, excused: distinct `staff_member_id` with that status on the organisation's current calendar date.
- Covering record: present, late, on leave, incomplete, or excused.
- Absent, derived: active staff in scope minus distinct people with a covering record. People with no row are included. This is a current figure, not a stored absent status.
- Absent, stored: used only when a location filter is set. It is the distinct people whose stored status is `absent` at that location. People with no row at that location are not called absent.
- Active staff: `staff_members.status = active` in the same department, person, and primary-location scope.
- A person with two records on the same day counts once in each status they actually have.

Permission: `attendance.view` on the organisation and department dashboards. The personal workspace always uses the viewer's own row.

### Attendance trend and status mix

Source: `attendance_records`.

- One row is one attendance record, not one person and not one scheduled period.
- The trend groups by `work_date` and `status`. A day with no rows is a zero, and the chart says so.
- Status mix includes every `AttendanceStatus`, including zeroes.
- Department mix omits records whose `department_id` is null. It keeps the department stored on the record, which may differ from the person's current department.
- Work-type mix joins `staff_members` on the same organisation and groups by current `work_type`. Two periods count twice.

### Verification methods

Source: `attendance_events` joined to `attendance_records` on the same organisation.

- Check-in events with outcome `accepted` and a method.
- Reserved methods (face, fingerprint, NFC) are omitted.
- Wi-Fi can appear only as support for another method. It is not a confirming method by itself.
- A zero means that method was not accepted in the range.

### Schedule coverage

Source: `shift_assignments` whose shift `status` is `published`.

- Scheduled: those assignments in the range.
- Attended: the assignment has a present or late `attendance_records` row for the same organisation and person, linked by `shift_assignment_id`, or by the legacy person-and-date match when that person has exactly one published assignment that day and the record has no session and no assignment.
- Draft shifts are excluded.
- Unassigned shifts: published shifts whose effective window overlaps the next 7 organisation days and that have no assignment in those 7 days. For a department scope, only shifts with that `department_id` are counted.
- Upcoming: published assignments in the next 7 days inside the scope.

### Leave

Source: `leave_requests` and `leave_balances`.

- Decision counts: requests whose `starts_on` is inside the selected range, grouped by status. This is not an attendance count.
- Pending: `submitted` requests in scope, including ones that start outside the chart range. The page says so.
- Utilisation: for balances whose period contains today, `sum(used_days) / sum(entitled_days + adjusted_days)` where the entitlement is greater than zero. A zero entitlement is left out of both sums. Pending days are not treated as used. The rate is null when no positive entitlement is open.
- Upcoming approved: approved requests that overlap the next 14 days.
- The personal workspace lists `LeaveBalance::available()` (entitled + adjusted − used − pending) for open periods. It does not recompute entitlement.

### Notifications

- Unread and read use `notifications.read_at`. Null is unread.
- Delivery uses `workforce_notice_deliveries.status` (`queued`, `sent`, `failed`, or unspecified). That is not read state.
- Organisation delivery totals require the organisation perspective. Department and personal views show only the viewer's unread count.

### Academic register

For an educational organisation, the operations dashboard counts active people with employment type or job title `Student`, job title `Lecturer`, and everyone else. This is the staff register. It is not class or course attendance. No course model exists.

## Charts

Server-rendered. `ChartPalette` maps a status key to a design token. Every chart has a text label, a title, and a screen-reader table. Pie is used for the status share only. Trends are stacked columns. Comparisons are bars. A zero is drawn as zero. There is no sample series in the authenticated charts.

## Caching

`ORBIT_ANALYTICS_CACHE_SECONDS` defaults to 60. Set it to 0 to disable. Keys look like `orbit-analytics:v1:{org}:{user}:{perspective}:{departments}:{staff}:{location}:{from}:{to}:{metric}`. Expiry is the only invalidation. Correctness does not depend on the cache. Tests set the lifetime to 0.

## Insights

Calculated notes appear on the analytics page, grouped as action required now or planning attention. They are not repeated as a block on the organisation dashboard. `DeterministicInsightProvider` may return up to four notes:

- Coverage gap (action required now): derived absent is at least 25% of active people, and active people are at least 5.
- Attendance decline: present + late records in the window are at most 80% of the previous window of the same length, and the previous window has at least 5.
- Incomplete rise (action required now): at least 3 incomplete records, and more than the previous window.
- Unassigned shifts: at least one published shift with no assignment in the next 7 days.
- Leave concentration: in one department of at least 5 active people, at least 40% have approved leave overlapping the next 14 days. At most 12 departments are inspected.

Each note names the period, the metric, and a next page. The badge says Calculated. Nothing is described as model-written.

## Not in this milestone

PDF, Excel beyond the existing attendance CSV, scheduled report delivery, a dashboard builder, an external model, course attendance, and per-tenant operational charts on the platform console.
