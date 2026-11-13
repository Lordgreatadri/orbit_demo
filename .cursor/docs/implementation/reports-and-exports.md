# Reports and exports

Implementation notes for ADR-0017. Builds on ADR-0016 analytics.

## Architecture

```
ReportRequest / query
  → ReportFilters (AnalyticsScope + narrow-only extras)
  → ReportRegistry → ReportProvider
  → ReportResult (KPIs, columns, rows)
  → Blade show page
  → ExportManager → CSV | XLSX | PDF | queued artifact
```

| Piece | Path |
| --- | --- |
| Contracts / result | `app/Reports/` |
| Providers | `app/Reports/Providers/` |
| Exporters | `app/Reports/Export/` |
| Tenant UI | `/org/reports`, `resources/views/tenant/reports/` |
| Platform UI | `/admin/reports`, `resources/views/platform/reports/` |
| Queued artifacts | `report_exports` + `GenerateReportExport` |

## Available reports

### Tenant (`analytics.view`)

| Key | Filters | Formats |
| --- | --- | --- |
| `attendance-summary` | department, location, person | CSV, XLSX, PDF |
| `attendance-by-person` | department, location, person, status | CSV, XLSX, PDF |
| `attendance-by-department` | department, location | CSV, XLSX, PDF |
| `attendance-detail` | department, location, person, status | CSV, XLSX |
| `punctuality` | department, location, person | CSV, XLSX |
| `incomplete-attendance` | department, location, person | CSV, XLSX |
| `verification-methods` | department, location, person, method | CSV, XLSX, PDF |
| `schedule-coverage` | department, location, arrangement | CSV, XLSX, PDF |
| `leave-summary` | department, person, leave_type, leave_status | CSV, XLSX, PDF |
| `notification-delivery` | (none; organisation perspective only) | CSV, XLSX, PDF |

### Platform (`platform.access`)

| Key | Formats |
| --- | --- |
| `platform-organisation-statistics` | CSV, XLSX, PDF |

## Authorization

- Server-side only. Hiding a nav item is not the control.
- Tenant: `analytics.view` + `AnalyticsScope` (organisation / department head / personal).
- Notification delivery is hidden/forbidden for department and personal perspectives.
- Platform: `platform.access`. No tenant person-level data.
- `org.analytics.export` reuses the attendance-detail provider and the same scope rules.

## Export behaviour

- Same filters and scope as the HTML report (`ReportFilters` → `AnalyticsScope` → provider). Clients never supply a row estimate; the server computes it.
- Organisation timezone for displayed date/times.
- Stable column keys/labels shared by HTML and exports. Exporters write only declared column keys, never raw model attributes.
- CSV streams. XLSX and PDF estimate rows first. Above 5 000 rows they queue and return before `build()`. Smaller files build only after that check. CSV stays synchronous.
- Queued jobs claim a row once (`queued`/`failed` → `processing`), rebuild filters as the original requester, and refuse a mismatched organisation id. A write failure is stored as `failed`, the partial file is removed, and the exception is rethrown so the queue can use its three attempts.
- Each artifact stores `generation_scope` (organisation, perspective, department ids, person id) from the scope that actually wrote the file.
- Downloads require the owning user, organisation, ready status, unexpired TTL, a path under `report-exports/{org}/{user}/`, the current report permission, and a current `AnalyticsScope` that still covers `generation_scope`. Losing a department or `analytics.view` rejects the file.
- `reports:purge-exports` (hourly) deletes expired and stale failed/processing rows, their files, and orphaned files under `report-exports/` that no remaining row references. A missing file does not stop metadata removal. Another tenant's live artifact is left in place.
- Detail, punctuality, incomplete, and leave exports load relationships with `lazy(200)`. On-screen pages for those row reports paginate in SQL (50) and take totals from aggregate queries.
- Leave-type and arrangement filters are part of the digest queries and of the analytics cache metric, so KPI tiles match the filtered rows.
- A person counted in more than one department during the window has each status summed, not overwritten.
- Rate limit: `reports-export` (20/min).
- Formula injection: `SpreadsheetSafety` prefixes `= + - @`.

## Performance

- Prefer SQL aggregates from `WorkforceReport` / `OperationalDigest`.
- Detail, punctuality, incomplete, and leave exports use `lazy(200)` so relationship eager-loading stays intact. Their HTML pages paginate in SQL (50). Totals come from aggregate queries.
- Existing attendance/leave/schedule indexes are sufficient for the first pass; no new index was required.
- Summary KPIs may use `AnalyticsCache` (same keys/TTL as analytics). Export rowsets are not cached.

## Date and timezone

- `work_date` / `starts_on` ranges: `>= from AND < following(to)` via `CalendarDay`.
- Window builder: `AnalyticsScope::window()` (max 61 day span / 62 inclusive days).
- User-facing timestamps converted with the organisation timezone.

## Extending

1. Add a provider under `app/Reports/Providers` implementing `ReportProvider`.
2. Reuse or extend `WorkforceReport` / `OperationalDigest` for calculations.
3. Register the class in `ReportRegistry`.
4. Add feature tests for authz, scope, dates, and export parity.

## Deferred

- Team filters (no team entity)
- Scheduled/email delivery
- Leave trend charts beyond digest figures
- Separate `reports.export` permission (exports inherit `analytics.view`)
