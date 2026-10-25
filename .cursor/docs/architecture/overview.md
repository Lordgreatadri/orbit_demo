# ORBIT Architecture Overview

## 1. Product and architecture intent
ORBIT (Online Real-Time Based Identification and Tracking System) is a web-first, API-ready workforce attendance and scheduling SaaS. It serves schools, businesses, public institutions, and other organisations through one platform. The EPA Ghana prototype is a tenant-specific demonstration configuration with fictional data.

The MVP is a Laravel modular monolith, not a collection of microservices. It should be simple to operate now and have clear seams for future mobile apps, provider integrations, analytics, and larger deployments.

## 2. Baseline stack
- Backend: the Laravel version already installed; target Laravel 12 or the current project-supported stable version only after checking compatibility.
- PHP: use the version supported by the installed Laravel and deployment environment; do not upgrade incidentally.
- Web: Blade, Tailwind CSS, Alpine.js, and small vanilla JS modules.
- Database: PostgreSQL preferred; respect an existing approved database configuration.
- Cache/queue: Redis where available, with Laravel queues for slow/retryable work.
- Auth: Laravel session authentication for first-party web flows; Sanctum when API tokens or first-party SPA/mobile needs justify it.
- Assets: the existing Vite/build setup.
- Files: Laravel storage abstraction; local for development and S3-compatible storage when required.
- Tests: Laravel feature/unit tests using the project's configured test runner.

## 3. Logical layers
1. Presentation: public Blade pages, authenticated dashboards, forms, accessible reusable components.
2. HTTP boundary: routes, middleware, Form Requests, controllers, API Resources.
3. Application/domain: use-case services/actions and policies for organisations, invitations, attendance, locations, schedules, leave, and notifications.
4. Integration boundary: contracts plus provider-specific adapters for SMS, email, maps/geocoding, payments (future), and external APIs.
5. Persistence: Eloquent models, migrations, database constraints, indexes, seeders.
6. Async/operations: jobs, queues, scheduled tasks, logs, metrics, backups, health checks.

Keep this separation pragmatic. Do not create an interface and repository for every model without a concrete reason.

## 4. Multi-tenant model
Use a shared application and shared database with tenant-owned rows explicitly linked to an organisation, unless an approved ADR later selects another model. Central platform tables (for example organisations and platform configuration) must be separated conceptually from tenant-owned business data.

Tenant-owned tables carry `organization_id` (or one consistently chosen equivalent). All tenant access is derived from authenticated context, not client-supplied values. Apply tenant-scoped query patterns and policies consistently, including exports, background jobs, notifications, and relationship writes. Every queued job that processes tenant data must carry a tenant identifier and re-establish/validate context.

## 5. Identity and provisioning
An organisation's responsible contact can register an organisation and become its initial organisation administrator after required verification. This is not an open employee/student sign-up system. Additional users are invited by authorised administrators or added by an approved bulk provisioning workflow. Platform staff accounts are provisioned separately and require explicit platform permissions.

Choose and document whether users are globally unique identities or tenant-specific accounts before implementing the full schema. For the initial implementation, prefer a clear, testable account model that prevents accidental cross-tenant access.

## 6. Identifier policy
All tables use a numeric internal `id` primary key. Externally addressable entities also have a UUID, protected by unique constraints. Route-model binding and public API URLs use UUIDs, not sequential IDs. Do not expose internal IDs in JSON, logs visible to tenants, or links.

## 7. First delivery sequence
1. Repository assessment and baseline tests.
2. Public marketing pages and shared design tokens/theme toggle.
3. Organisation registration, login/logout, password reset, email verification where configured.
4. Tenant bootstrap, roles/permissions, organisation settings, and invitation foundation.
5. EPA demo tenant and clearly labelled fictional seed data.
6. Admin dashboard shell and module navigation.
7. Employees/users, locations, attendance, and check-in flows in planned increments.
8. Scheduling, leave, notifications, analytics, and exports.
9. Harden, test, document, and prepare deployment.

Do not implement all modules in one unreviewable change.

## 8. Scalability and availability
- Keep web nodes stateless where possible.
- Use PostgreSQL constraints, targeted indexes, pagination, aggregate queries, and query-count profiling.
- Offload emails, SMS, exports, and heavy aggregation to queues.
- Keep provider credentials outside code and support per-environment configuration.
- Define cache invalidation and tenant-safe keys before caching tenant data.
- Add health checks, centralised logs, queue monitoring, automated backups, restore drills, and deployment rollback guidance before production.
- Do not claim real-time updates unless an implemented mechanism exists. The demo can initially use polling or normal refresh; later add broadcasting where justified.

## 9. Security and privacy
Use Laravel security primitives, policies, rate limiting, secure sessions, validation, audit trails, secret management, and least privilege. GPS coordinates should be captured only for explicit attendance verification, not continuous employee surveillance. Keep demo data fictional. Before production, establish data retention, privacy notice, incident response, backup access controls, and jurisdiction-appropriate legal review.

## 10. API conventions
Future API routes should be versioned under `/api/v1`, use JSON, stable UUID identifiers, resource-oriented naming, consistent validation/error responses, pagination, authorization, rate limits, and API tests. Do not build a parallel API layer merely for theoretical future use; keep domain logic reusable from web and API boundaries.
