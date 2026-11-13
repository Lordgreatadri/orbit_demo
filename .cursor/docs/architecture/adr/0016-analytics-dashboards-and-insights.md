# ADR-0016: Analytics scope, dashboards, and deterministic insights

- Status: Accepted
- Date: 2026-11-11

## Context

Attendance, schedule, and leave figures already lived in `WorkforceReport` and in the organisation dashboard controller. The dashboard was one page for every signed-in organisation user, including people who should only see their own record. Charts were server-rendered SVG and CSS, which matches the Blade-first decision in ADR-0004. There is no approved external model, and product scope excludes AI analytics and fraud scoring.

## Decision

- Keep `WorkforceReport` as the attendance, adherence, and arrangement source. Dashboards, the analytics page, and the CSV export call it. They do not keep a second formula.
- `AnalyticsScope` decides the rows a user may aggregate. An organisation administrator, or anyone with organisation-wide analytics who does not head a department, sees the tenant. A non-admin who heads one or more departments sees only those departments. Everyone else sees only their own person record. A department or location filter may narrow that scope. It cannot widen it and cannot name another organisation.
- `OperationalDigest` adds schedule coverage, leave-year utilisation, and notification delivery versus read counts. Notification delivery totals are organisation-wide and are not shown to a department or personal scope.
- `DashboardComposer` builds one payload. The personal workspace does not run organisation aggregate queries.
- Charts stay server-rendered. `ChartPalette` is the shared status colour. No second charting library.
- Insights implement `OperationalInsightProvider`. The bound implementation is `DeterministicInsightProvider`. It quotes stored counts and fixed thresholds. It does not call an external model, and it cannot change records. A later model must implement the same contract and must not query tenant tables itself.
- Aggregates may be cached for `ORBIT_ANALYTICS_CACHE_SECONDS` (default 60, tests use 0). The key includes organisation, viewer, perspective, department ids, location, staff member, and dates. Tenant id alone is not a cache key.
- The platform dashboard counts organisations, accounts, and sign-in events. It does not chart a tenant's attendance, leave, or schedules.
- Platform statistics (`App\Analytics\PlatformStatistics`, `/admin/statistics`) extends that rule: growth, organisation type and status, account health, register composition by status and work type, invitation outcomes, and sign-in outcomes. Register counts are aggregates; no member name is read. The workforce overview shows per-organisation counts of distinct people today and never lists individuals.
- PDF, Excel, and the reusable reports catalog are owned by ADR-0017. This milestone keeps the attendance CSV, scoped by the same `AnalyticsScope` (now also an alias of the attendance-detail report export). Scheduled report delivery remains later.

## Consequences

- A department head who also has `analytics.view`, and who is not an organisation administrator, sees only the departments they head. That limit applies to the dashboard, analytics, CSV export, attendance register, staff register, schedule lists and mutations, and the leave approval queue. An organisation manager who heads no department keeps organisation-wide access. `organization.update` and `organization_admin` stay organisation-wide even when that person heads a department.
- Today's absent figure is derived (active people minus people with a covering record) unless a location filter is set. A location filter counts the stored absent status. The trend counts attendance records, so two periods on one day count twice. Today's present and late tiles count distinct people.
- Draft shifts are excluded from adherence and arrangement mix. Leave utilisation uses open-period balances (`used / (entitled + adjusted)`) and ignores a zero entitlement.
- Ordinary analytics still render when the insight provider returns nothing.
