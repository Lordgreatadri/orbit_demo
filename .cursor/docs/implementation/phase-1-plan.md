# Phase 1 Implementation Plan — Start Here

## Stage 0: Repository reconnaissance (no broad edits)
1. Inspect current Laravel/PHP versions, dependencies, routes, middleware, migrations, models, auth implementation, frontend build, tests, and deployment assumptions.
2. Run available baseline tests/builds if safe and report results.
3. Compare repository state with this pack; list missing pieces, conflicts, risks, and recommended order.
4. Do not upgrade frameworks or replace existing working packages without explicit justification.

**Deliverable:** concise repository assessment and file-level implementation plan.

## Stage 1: Design foundation and public pages
1. Establish reusable layout, navigation, footer, buttons, form controls, alerts, status badges, and empty states.
2. Add design tokens and accessible light/alternate theme toggle.
3. Build landing page and public informational pages: About/How it works/User Guide, Contact, Terms, Privacy.
4. Add page metadata, responsive navigation, validation, accessible feedback, and realistic copy.
5. Do not create fake live metrics or unsupported integrations.

**Acceptance:** responsive, accessible public pages; theme works consistently; tests cover contact form validation and route visibility.

## Stage 2: Authentication and organisation onboarding
1. Inspect current auth package/scaffolding and reuse it where sound.
2. Implement login/logout, password reset, and verification where mail configuration supports it.
3. Implement organisation registration for the responsible contact/initial organisation administrator.
4. Create organisation and initial user/membership in one transaction.
5. Add role/permission boundaries, protected route middleware, policies, rate limits, and UUID route binding.
6. Explicitly ensure there is no public member/student/employee registration.

**Acceptance:** auth and onboarding feature tests pass; no partial records on failure; unauthorised and cross-tenant tests pass.

## Stage 3: Tenant foundation and demo data
1. Finalise user-to-tenant relationship based on the existing schema and product decision.
2. Add tenant scoping conventions and tenant-aware policies.
3. Create fictional EPA demo seed data only, guarded so it is not loaded into production by accident.
4. Create local-only demo accounts using safe setup instructions; do not commit real passwords.
5. Build an empty-state admin shell and role-aware navigation.

**Acceptance:** demo is visibly labelled; all seeded data is fictional; isolation tests pass.

## Stage 4: Operational modules in priority order
1. Locations and departments.
2. Member directory/profile and invitations.
3. Attendance records and office QR sessions.
4. Field GPS/geofence check-in.
5. Schedules and assignments.
6. Leave requests/approval.
7. Notifications.
8. Basic analytics and exports.

Each module must have requirements, migrations, policies, tests, empty/loading/error states, and documentation before moving to the next.

## Stage 5: Hardening and delivery
- Run full tests, formatters/static analysis already installed, frontend build, and migration checks.
- Inspect authorization, tenant boundaries, N+1 queries, indexes, secrets, and error handling.
- Document local setup, queue configuration, seed commands, and known limitations.
- Provide a summary of files changed, commands executed, results, and follow-up risks.

## Rules
- Do not execute Git pull/commit/push/branch-changing commands.
- Do not build billing, payment, payroll, BLE, biometrics, AI, or real EPA integrations in this plan.
- Do not claim a feature is complete unless its acceptance criteria are met and tests have been run.
