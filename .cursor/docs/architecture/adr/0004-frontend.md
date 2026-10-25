# ADR-0004: Blade-first web experience

- Status: Accepted
- Date: 2026-10-24

## Context
The platform documentation recommends Blade, Tailwind, Alpine.js, and vanilla JS for rapid MVP delivery and low complexity.

## Decision
Use server-rendered Blade pages with reusable components and progressive enhancement. Keep the application PWA-ready with offline support, but do not require a separate SPA or native app for the first milestone.

## Consequences
- Fast initial delivery and straightforward Laravel authentication/CSRF integration.
- Keep client JavaScript small and accessible.
- Revisit a richer client architecture only if concrete mobile interaction needs justify it.
