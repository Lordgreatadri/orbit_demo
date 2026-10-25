# ADR-0008: Light-first theme with a distinctive alternate theme

- Status: Accepted
- Date: 2026-10-24

## Decision
Use a clean, high-contrast light theme by default and a user-toggleable alternate theme based on deep teal and midnight plum rather than pure black. Use semantic design tokens and accessible status indicators.

## Consequences
Theme choice must be applied consistently to public pages, forms, tables, dashboards, dialogs, and charts. Respect system preferences where useful, but default to light. Test contrast and keyboard access; status meaning must not depend on colour alone.
