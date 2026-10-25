# ADR-0006: PostgreSQL-first persistence and query discipline

- Status: Accepted, subject to existing repository configuration
- Date: 2026-10-24

## Decision
Prefer PostgreSQL for production because the source architecture identifies its transactional, analytics, and geospatial capabilities. Retain existing database configuration if the repository has a deliberate working choice; report it before proposing a migration.

Use foreign keys, unique constraints, targeted indexes, pagination, eager loading, aggregate queries, and transaction boundaries. Use Redis for queues/cache/rate limiting when configured and appropriate.

## Consequences
Database portability should be considered but must not weaken correctness. GIS/geofencing capabilities should be introduced only when the relevant location features are implemented and tested.
