# ORBIT Visual System

## Design direction
Professional, calm, modern, data-focused, and suitable for educational institutions, businesses, and public-sector demonstrations. Prioritise clarity and trust over decorative effects. The EPA tenant should feel credible and operational, not like a generic template dashboard.

## Themes
### Default light
- Page background: soft cool off-white.
- Surfaces: white with subtle borders.
- Primary: deep teal/green for ORBIT identity and primary actions.
- Secondary accent: muted blue for information.
- Text: deep slate with strong contrast.
- Avoid large saturated colour blocks and unnecessary gradients.

### Alternate: deep teal / midnight plum
- Page background: very deep teal-charcoal, not pure black.
- Surfaces: slightly lighter teal-charcoal.
- Primary actions: clear teal with sufficient contrast.
- Secondary accent: muted lavender/plum for selected emphasis.
- Text: warm off-white; secondary text must remain readable.
- Do not make every card a different colour. Keep surfaces restrained.

## Semantic status palette
- Present/success: green.
- Late/attention: amber/yellow.
- Absent/critical: red.
- Informational/neutral: blue.
- On leave: use a distinct neutral/purple treatment only if consistently defined.
Always include a readable text label and, where helpful, an icon. Colour alone must never communicate the status.

## Component guidance
- Navigation: clear active state, keyboard accessible, collapsible on mobile.
- Forms: persistent labels, inline validation, helpful descriptions, visible focus.
- Tables: clear hierarchy, mobile strategy, pagination, empty/loading/error states.
- KPI cards: meaningful label, value, date range/context, no fake precision.
- Charts: meaningful labels and accessible fallback/table where practical.
- Maps: disclose sample/demo data and avoid claiming real-time location unless truly connected.
- Theme control: labelled toggle/select, keyboard operable, persisted preference where safe.

## Accessibility
Target WCAG 2.2 AA as an engineering goal. Verify contrast, focus visibility, semantic structure, keyboard operation, form errors, and reduced motion. Automated tools supplement, but do not replace, manual checks.
