# ORBIT visual system

The authenticated product is an operations workspace. The public site uses the same brand and type, with a more editorial register. Light is the default. Deep teal is the alternate theme and uses the same hierarchy.

The guiding rule is **clear hierarchy and intentional structure**, not minimalism. Cards, borders, icons, and semantic colour are used where they help a reader scan, compare, or act. They are not removed for the sake of flatness, and they are not added for decoration. Milestone 5 refinement reversed an earlier flat pass that put every section on the page background and made the dashboard read like a form.

## Typography

Instrument Sans. Sentence case.

| Role | Size | Weight |
| --- | --- | --- |
| Page title | 1.75rem, 2rem from 768px | 600 |
| Briefing statement | 1.1875–1.5rem | 600 |
| Section title (`.section-title`) | 1.0625rem | 600 |
| Panel title | 1rem | 600 |
| KPI value | 1.75rem, 1.875rem from 640px, tabular | 600 |
| KPI label | 0.8125rem | 500 |
| Body | 0.9375rem | 400 |
| Secondary | 0.875rem | 400 |
| Metadata | 0.75rem | 400 |
| Chart axis | 0.6875rem | 400 |

## Surfaces

| Token | Light | Deep teal |
| --- | --- | --- |
| Page | `#f4f7f9` | `#0b1d21` |
| Surface | `#ffffff` | `#11282d` |
| Inset | `#eef2f5` | `#0e2226` |
| Edge | `#d8dfe6` | `#26444a` |
| Text | `#16212b` | `#f4efe8` |
| Primary | `#0f766e` | `#34d1bd` |

Surface classes in `resources/css/app.css`:

- **`.briefing`.** The single Level 1 surface on a dashboard or analytics page. Primary-tinted edge, a faint primary and accent wash, resting shadow.
- **`.card`.** A panel for a distinct object: a KPI, chart, workflow list, form, or table. Hairline edge, `rounded-xl`, resting shadow (`--shadow-panel`). `x-card` adds an optional title icon, actions, and a `decision` footer.
- **`.card-link` / `.card-hover`.** Interactive panels. Hover strengthens the edge and lifts to `--shadow`.
- **`.table-panel`.** Applied by `x-data-table`. A table is one panel. Inside an existing `.card` the frame is dropped, so tables never nest borders.
- **`.toolbar`.** A bounded filter row (`x-filter-bar`, attendance filters).
- **`.inset`.** A quiet muted group with no edge. Use inside a panel, never around panels.
- **Overlay.** Menus, dialogs, and the mobile drawer keep `--shadow-lift`.

Do not wrap a heading and a paragraph in a card. Do not nest a card in a card. Do not put cards inside an inset.

## Hierarchy

1. **Immediate attention.** `.briefing`. One statement that summarises the scope, then prioritised rows with a severity rail (`.rail-danger`, `.rail-warning`), an icon well, the period, and one action.
2. **Current position.** KPI tiles (`x-stat`). Label, figure, a restrained 2rem icon well (`.icon-well` + `.tone-*`), an optional share meter, and a hint that says what the figure counts. A figure that needs attention uses `emphasis` (red or amber top edge and figure colour). Linked tiles show an arrow on hover.
3. **Trends and analysis.** `x-card` panels with a conclusion as the title, a one-line method, the chart, and a `decision` footer ("Informs …"). Grids are 2/3 + 1/3 or thirds.
4. **Supporting information.** Notifications and administration links. Quieter panels, no meters.

Every visualisation states the decision it supports through the `decision` footer on `x-card`.

## Iconography

Icons come from `x-icon` (16px, stroke 1.7). In KPI tiles and list rows they sit in a 2rem icon well with a soft semantic tone. Panel titles use a bare 16px muted icon. Icons are never the only carrier of meaning and are never added only to fill space.

## Colour

Present → success green. Late → warning amber. Uncovered or exception → danger red. On leave → accent. Informational → info blue. Primary action → teal. Whole cards are not coloured; colour appears in the icon well, rail, meter, figure, badge, or top edge.

## Spacing and controls

Section rhythm in the authenticated main column is `space-y-8`. Grid gaps are `gap-3`/`gap-4` for KPI tiles and `gap-6` for panels. Page inset is 1rem, 1.5rem from 640px, 2rem from 1024px. Dashboards and analytics use the wide layout (`max-w-[90rem]`); reading pages use `max-w-6xl`.

Buttons, text fields, selects, filter controls, navigation rows, and the top-bar controls use a 44px minimum height. Primary actions are teal. Secondary actions are outlined. Filter fields are flexible (`flex-1` with a minimum width) so the apply button stays on the row.

## Dashboard composition

Organisation and department dashboards:

1. **Header.** Greeting, date and scope, primary actions.
2. **Operational briefing.** Left: the summary statement, the time it was calculated, and a covered-today meter. Right: needs attention and upcoming decisions, each with one action. With nothing pending, it says so.
3. **Today's position.** Active staff, present, late, on leave, uncovered. Uncovered spans the full width on phones.
4. **Trend and coverage.** 14-day stacked trend (2/3) and today's coverage donut (1/3) whose ring adds up to active staff.
5. **Operations.** Schedule coverage gauge with upcoming assignments, leave decisions with utilisation, workforce composition.
6. **Supporting.** Notifications (2/5) and an administration link grid (3/5).

The personal workspace uses a briefing with today's status and the next shift, three KPI tiles (unread, open requests, annual leave available), recent attendance, balances, requests, and notices. It never shows organisation figures.

The platform console uses the same grammar: platform briefing with signals or passed health checks, five KPI tiles, registrations and tenant-health donut, SMS delivery by day, security, and recent organisations and audit entries. It never opens tenant attendance, leave, or schedules. *Statistics* (`/admin/statistics`) adds 12-month growth, organisation type and status, register composition, sign-in outcomes, invitation acceptance, account health, and the largest organisations. *Workforce overview* (`/admin/workforce`) shows today's coverage per organisation as distinct people, in each organisation's local date, without names.

## Forms

Password fields have a 44px eye button inside the field that toggles visibility. The input stays `type="password"` until the person presses it; the button carries `aria-pressed`, `aria-controls`, and a Show or Hide label.

## Disclosure (FAQ)

Questions use native `<details>`/`<summary>`: the question is always visible, the plus icon turns into a close mark when open, and each answer opens and closes independently. No JavaScript is needed.

## Insights

Insight notes (`x-insight-list`) render as "Operational intelligence" on the analytics page. They are produced by `DeterministicInsightProvider` from fixed thresholds, carry a **Calculated** badge, and say that no external model is used (ADR-0016). They are never labelled AI-generated.

## Tables

A register is a heading, a toolbar, then one table panel. Headers are sentence case and sticky inside the horizontal scroller. Rows have a hairline divider and a hover fill. The person's or entity's name is the primary text. Status is a text badge. Row actions are links. Wide tables scroll inside the table, not the page.

## Charts

Charts are server-rendered Blade/SVG. There is one palette, `App\Analytics\ChartPalette`. Components:

- `x-charts.stacked`: daily stacked columns with gridlines, a bold latest-day label, and a legend with range totals.
- `x-charts.columns`: categorical columns with gridlines; zero is a hairline.
- `x-charts.donut`: share of a whole, total in the centre, every category listed with count and share (`pie` delegates here).
- `x-charts.bars`: horizontal comparison bars for worded categories.
- `x-charts.gauge`: a single rate; `null` draws a dash, never 0%.

Each chart has a conclusion, a short method caption, and a screen-reader table. Zero data stays visible as a hairline or a listed zero. No data in range is a sentence, not an empty plot. No 3D, decorative motion, or second chart library.

## Shell

The sidebar (17rem) carries the ORBIT mark, a workspace card with a monogram, the organisation name (two lines), type and demo badge, grouped navigation with a rounded active fill and a primary bar, and the signed-in person. The top bar carries the date on desktop, the workspace name on small screens, notifications, theme, and account. The sidebar is a drawer below 1024px.

## Responsive rules

- **390px.** No page-level horizontal scrolling. Briefing first, then a two-column KPI grid; uncovered spans the width. Briefing actions stack under their statement. Charts scroll inside their panel. Donut legends stack under the ring (container query).
- **768px.** Three-column KPI grid, panels stack, the sidebar stays a drawer.
- **1280px.** Persistent sidebar. Five-column KPI strip, 2/3 + 1/3 analysis row, three-column operations row.

## Public pages

Home, how it works, the user guide, registration, and sign-in share type, teal, buttons, and form controls with the workspace, but use an editorial layout: a hero with an illustrative workspace preview (labelled **Illustrative**, sample values, no live data), an audience band, problem-to-outcome cards, capability cards that state the question each answers, a four-step process, institutional safeguards, and the brand-panel call to action. The brand panel is the one dark public moment. The site does not claim customers, endorsements, certifications, or government partnerships.

## Accessibility

Target WCAG 2.2 AA. Visible focus, semantic headings, text for status, 44px targets, and a table alternative for every chart. Colour is never the only status signal. Reduced motion removes transitions.
