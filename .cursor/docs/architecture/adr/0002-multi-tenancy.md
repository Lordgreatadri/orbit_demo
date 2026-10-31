# ADR-0002: Shared-database multi-tenancy

- Status: Accepted for MVP; validate against existing repository before migration design
- Date: 2026-10-24

## Context
ORBIT must support schools, businesses, ministries, and the EPA demo on one platform. Tenant data must remain isolated without requiring separate deployments for each customer.

## Decision
Use a shared application and shared database for the MVP. Tenant-owned records include a required organisation foreign key. Apply tenant-scoped query access, policies, route binding, job context, and automated isolation tests. Keep platform administration distinct from tenant administration.

Hostname routing and organisation branding are **separate** from this isolation model (ADR-0011). The current tenant is resolved from a registered hostname, then `organization_id` scoping continues to enforce data boundaries. Do not treat the organisation slug as the public URL; organisations have a domain registry that supports a primary host, aliases, and future custom domains.

## Consequences
- Cost-effective and operationally simple for early adoption.
- A tenant leak is a critical security defect; isolation tests are mandatory.
- Indexes must reflect tenant-scoped access patterns.
- Dedicated databases or other tenancy strategies remain future options requiring a separate ADR and migration plan.
