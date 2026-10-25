# ORBIT — Cursor Engineering Pack

This pack consolidates the ORBIT Web-First Laravel SaaS architecture, EPA Ghana demonstration requirements, and product positioning into implementation-oriented Markdown documentation and Cursor rules.

## How to install
1. Back up the current project and inspect its existing Laravel/PHP versions and conventions.
2. Copy the `.cursor/` and `/docs/` directories from this pack into the root of the ORBIT repository.
3. Read `.cursor/rules/00-project-governance.mdc`, `.cursor/docs/architecture/overview.md`, `.cursor/docs/requirements/00-product-scope.md`, and `.cursor/docs/implementation/phase-1-plan.md`.
4. Ask Cursor to inspect the repository and report a gap analysis against these documents before it edits code.
5. Implement one milestone at a time. Review diffs and run tests after every milestone.

## Pack structure
- `.cursor/rules/`: persistent, scoped engineering rules for Cursor.
- `.cursor/docs/architecture/`: architecture overview and Architecture Decision Records (ADRs).
- `.cursor/docs/requirements/`: product scope, functional requirements, non-functional requirements, and acceptance criteria.
- `.cursor/docs/implementation/`: staged execution plan and definition of done.
- `.cursor/docs/design/`: visual system and interaction guidance.
- `.cursor/docs/integrations/`: provider integration boundaries and source API references.

## Authority and conflict resolution
1. Explicit user direction and approved project decisions.
2. `.cursor/docs/architecture/adr/` decisions.
3. `.cursor/docs/requirements/` requirements and acceptance criteria.
4. `.cursor/docs/architecture/overview.md`.
5. Cursor rules and implementation plan.
6. Existing code conventions, unless they conflict with an approved decision.

If the repository differs from this plan, inspect and report the difference before changing frameworks, major dependencies, or database architecture. Do not silently rewrite or upgrade a working project.

## Key product rules
- ORBIT is one multi-tenant product. EPA Ghana is the first demo tenant, not a separate codebase.
- Public self-service registration creates an organisation/tenant and its responsible contact/initial organisation administrator only.
- Students, employees, staff, field officers, and other tenant members must not self-register. They are invited by authorised tenant administrators or provisioned by an approved import workflow.
- Every table's primary key is an internal numeric `id`; add a UUID to relevant domain entities and use UUIDs for public route-model binding. Never expose sequential IDs in public URLs or API contracts.
- Use Laravel conventions and a modular monolith; do not introduce microservices for the MVP.
- Do not run `git pull`, `git commit`, or other Git commands that change branches or publish changes. The user owns branch creation, commits, and pulls. Read-only Git inspection is permitted only when useful.
- First milestone: public pages, organisation registration, authentication, password reset, and role/tenant foundations. Build other business modules one at a time.
