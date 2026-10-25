# ADR-0002: Shared-database multi-tenancy

- Status: Accepted for MVP; validate against existing repository before migration design
- Date: 2026-10-24

## Context
ORBIT must support schools, businesses, ministries, and the EPA demo on one platform. Tenant data must remain isolated without requiring separate deployments for each customer.

## Decision
Use a shared application and shared database for the MVP. Tenant-owned records include a required organisation foreign key. Apply tenant-scoped query access, policies, route binding, job context, and automated isolation tests. Keep platform administration distinct from tenant administration.

## Consequences
- Cost-effective and operationally simple for early adoption.
- A tenant leak is a critical security defect; isolation tests are mandatory.
- Indexes must reflect tenant-scoped access patterns.
- Dedicated databases or other tenancy strategies remain future options requiring a separate ADR and migration plan.
