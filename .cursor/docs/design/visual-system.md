# ORBIT visual system

The authenticated product is an operations record. The public site uses the same brand and type, with a more explanatory register. Light is the default. Deep teal is the alternate theme and uses the same hierarchy.

Containment is reserved for charts, filters, forms, decision areas, dialogs, and tables when a boundary helps scanning. Ordinary content uses whitespace, type, alignment, and dividers.

## Typography

Instrument Sans. Sentence case.

| Role | Size | Weight |
| --- | --- | --- |
| Page title | 1.75rem, 2rem from 768px | 600 |
| Attention statement | 1.0625rem | 500 |
| Section title | 1rem | 600 |
| KPI value | 1.75rem, tabular | 600 |
| KPI label | 0.8125rem | 500 |
| Body | 0.9375rem | 400 |
| Secondary | 0.875rem | 400 |
| Metadata | 0.75rem | 400 |
| Table | 0.875rem | 400 |

KPI labels are sentence case. Navigation group labels are sentence case. Status is a word, with colour as a second signal.

## Surfaces

| Token | Light | Deep teal |
| --- | --- | --- |
| Page | `#f4f7f9` | `#0b1d21` |
| Surface | `#ffffff` | `#11282d` |
| Inset | `#eef2f5` | `#0e2226` |
| Edge | `#d8dfe6` | `#26444a` |
| Text | `#16212b` | `#f4efe8` |
| Primary | `#0f766e` | `#34d1bd` |

- **Open.** Most sections sit on the page. No border and no shadow.
- **Inset.** Attention and KPI strips. Muted fill, 0.5rem radius, no border.
- **Panel (`.card`).** Chart, form, or dialog. One hairline edge, no float shadow.
- **Overlay.** Menus, dialogs, and the mobile drawer keep a lift shadow.
- **Divider.** One-pixel edge between rows and secondary sections.

Hover on a row or a linked figure is a muted surface or an underline. Active navigation is a primary-soft fill with a 2px primary bar on the left. Focus is a 2px focus ring, offset 2px.

## Spacing and controls

Section rhythm in the authenticated main column is `space-y-10` (2.5rem). Page inset is 1rem, 1.5rem from 640px, 2rem from 1024px. The default reading width is `max-w-6xl`. Registers that need a wide table use the wide layout.

Buttons, text fields, selects, filter controls, navigation rows, and the top-bar controls use a 44px minimum height. Primary actions are teal. Secondary actions are quiet. Icons are 16px and monochrome. KPI figures do not use a coloured icon chip.

## Dashboard hierarchy

Organisation and department dashboards read in this order:

1. **Header.** One title (the greeting). Scope and date are the description. The organisation name lives in the sidebar on desktop and in the top bar on small screens. It is not repeated in the page header.
2. **Attention.** Two groups: Needs attention (uncovered people today) and Upcoming decisions (unassigned published shifts, leave awaiting a decision). Each item has a statement, a period, and one action link.
3. **Position.** One strip: Active staff, On leave, Present today, Late today, Uncovered today. Hints say whether a figure is a register status, a stored attendance status, or derived from the absence of a covering record. The same figures are not repeated in a Today card.
4. **Trend.** A sentence states the last-14-day conclusion. The chart is the evidence, with its screen-reader table. A range with no records does not draw an empty plot.
5. **Secondary reading.** One column: workforce composition, schedule coverage, upcoming assignments, leave waiting, and a short notification summary that routes to the inbox.
6. **Administration.** A metadata link row. Audit and sign-ins sit in that row, not in a competing card.

When analytics caching is enabled, one line under the position strip says the figures can be up to that many seconds old. Tests disable the cache, so the line is absent there.

A personal workspace uses the same strip and reading order for that person's today, recent attendance, leave, and notices. It does not show organisation KPIs.

## Tables

A register is a heading, an inset filter bar, then the table. The table is not wrapped in a decorative card. Headers are sentence case and sticky inside the horizontal scroller. Rows have a hairline divider and a hover fill. The person's or entity's name is the primary text. Status is a text badge. Row actions are links. Wide tables scroll inside the table, not the page.

## Charts

Charts are server-rendered. There is one palette, `App\Analytics\ChartPalette`. A chart has a conclusion, a short methodological caption, the plot, and a screen-reader table. Zero-data (a day or status with a stored zero) stays visible as a hairline or a listed zero. No-data (nothing in the range) is a sentence, not an empty plot. No 3D, decorative motion, or second chart library.

## Shell

The sidebar carries the ORBIT mark, the workspace name, grouped navigation, and the signed-in person. The top bar carries the date on desktop, the workspace name on small screens, notifications, theme, and account. The mobile sidebar is a drawer below the `lg` breakpoint and persistent from 1024px.

## Responsive rules

- **390px.** No page-level horizontal scrolling. Attention comes before the position strip. The strip is two columns; uncovered spans the full width when it is above zero. The chart scrolls inside itself. Filters and the primary action stay reachable.
- **768px.** The same order. The sidebar stays a drawer.
- **1280px.** Persistent sidebar. One reading column. No right-hand widget rail.

## Public pages

Home, how it works, the user guide, registration, and sign-in use the same type, teal, and light surface. They are more editorial than the workspace: a hero, a short product story, and factual calls to action. The home preview is labelled illustrative and does not show live figures. The brand panel is the one dark public moment. The site does not claim customers, endorsements, certifications, or government partnerships.

## Accessibility

Target WCAG 2.2 AA. Visible focus, semantic headings, text for status, 44px targets, and a table alternative for every chart. Colour is never the only status signal. Reduced motion removes transitions.
