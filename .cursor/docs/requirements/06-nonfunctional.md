# Non-Functional Requirements

## Maintainability
- Prefer Laravel-native structure and clear naming.
- Keep controllers thin and domain rules testable.
- Avoid unnecessary dependencies, overengineering, duplicate validation, and giant files.
- Document non-obvious decisions and integration contracts.

## Performance
- Paginate all potentially large lists.
- Eager-load relationships and test for N+1 queries on important screens.
- Use indexed tenant/date/status filters and aggregate database queries for dashboards.
- Queue slow email/SMS/export work.
- Define cache TTL, tenant-aware keys, and invalidation before caching business data.
- Establish performance baselines and realistic seeded test volumes before claiming capacity.

## Availability and recovery
- Make application instances stateless where practical.
- Use durable shared storage/session/queue infrastructure for multi-instance deployments.
- Configure database backups and test restoration.
- Monitor queue depth, failed jobs, application errors, database health, disk capacity, and latency.
- Document health checks, deployment rollback, and recovery responsibilities.
- Avoid promising high availability without deployed redundancy, monitoring, and tested recovery.

## Security and privacy
- TLS in deployed environments, secure sessions/cookies, CSRF protection, output escaping, password hashing, rate limits, and least privilege.
- Tenant isolation and authorization tests are release blockers.
- Secrets only in environment/secret management.
- Minimise collection and retention of location and personal data.
- Maintain safe audit trails and avoid sensitive payload logging.
- Legal terms and privacy content require review before production.

## Accessibility and usability
- Responsive layouts, keyboard navigation, visible focus, semantic controls, accessible validation and status announcements, and sufficient contrast.
- Status labels cannot depend on colour alone.
- Theme toggle must be operable with keyboard and assistive technologies.

## Observability
- Use structured application logs and correlation identifiers where appropriate.
- Capture exceptions through a configured monitoring service when available.
- Never log passwords, tokens, secret headers, or unnecessary precise location information.
- Document how to inspect queue failures and application health.
