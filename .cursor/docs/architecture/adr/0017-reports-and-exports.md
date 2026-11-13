# ADR-0017: Reports contract and export formats

- Status: Accepted
- Date: 2026-11-12

## Context

ADR-0016 established `AnalyticsScope`, `WorkforceReport`, and `OperationalDigest` as the single source of operational figures, with one attendance CSV on the analytics page. Product now needs a reusable reports layer (on-screen and exportable) for tenant operators and platform aggregates, without a second KPI formula stack or a charting/reporting package.

## Decision

- Add `App\Reports` as an extension of analytics, not a parallel system. Each report implements `ReportProvider` with a `ReportDefinition` (filters, formats, audience). `ReportRegistry` resolves providers by key.
- Controllers resolve `AnalyticsScope` through `ReportFilters`, then ask the provider for a `ReportResult`. KPI SQL stays in `WorkforceReport` / `OperationalDigest` / `PlatformStatistics`.
- Tenant reports require `analytics.view`. Platform reports require `platform.access` and never expose tenant member names or tenant attendance/leave/schedule detail.
- Filters may only narrow scope. Person, department, and location UUIDs outside the viewer’s window are ignored or yield empty results; they cannot widen access.
- Dates use organisation timezone and `CalendarDay` half-open bounds. Report windows reuse `AnalyticsScope::window()` (at most 62 inclusive days).
- Exports share one `ExportManager`: CSV streams; relationship exports load with `lazy(200)`; XLSX uses OpenSpout streaming; PDF uses Dompdf for summary-friendly reports only. Formula-leading cells are escaped via `SpreadsheetSafety`.
- When an XLSX/PDF estimate exceeds 5 000 rows, enqueue `GenerateReportExport` before building the on-screen result, store the artifact on the local disk, and let the requester download it. CSV stays synchronous. The estimate is server-computed.
- The artifact records the generation scope. A later download is allowed only when the requester still has the report permission and their current `AnalyticsScope` covers that stored scope.
- Queued exports are claimed once. A failure is recorded and rethrown so the queue retries. Files live under `report-exports/{org}/{user}/…` and are purged, with orphan files, by `reports:purge-exports`.
- Keep `/org/analytics/export` as a thin alias of the attendance-detail CSV so existing links keep working.

## Consequences

- New operational reports are added by registering a provider; infrastructure is not copied.
- Department-head limits from ADR-0016 apply unchanged to report HTML and every export format.
- Scheduled/email delivery, team filters (no team entity), and fabricated availability metrics stay out of scope.
- Cache for summary KPIs may reuse `AnalyticsCache`; export rowsets are not cached.
