# Tenant and Organisation Requirements

## Organisation data
At minimum, plan fields for numeric id, UUID, name, organisation type/industry, status, logo/storage reference, timezone, contact details, and timestamps. Subscription-plan references may be reserved for later but must not activate billing in the EPA demo.

## Isolation
- Tenant-owned entities must have a tenant/organisation relationship.
- Derive tenant context from the authenticated account and authorised membership.
- Scope reads, writes, relationship attachment, exports, notifications, and background jobs.
- Platform operators may cross tenant boundaries only through explicit privileged paths and audited actions.
- Do not trust tenant identifiers supplied by the browser.
- Ensure tenant context is reset between queue jobs and requests.

## Organisation lifecycle
Support defined states such as pending verification, active, suspended, and closed only where lifecycle requirements justify them. Deny sensitive operations for inactive/suspended tenants and document recovery behaviour.

## Customisation
The long-term platform may allow organisation branding and settings. For the initial milestone, support essential organisation profile settings and logo only if straightforward; do not implement full white-labeling in the EPA demo.

## Acceptance criteria
- An organisation administrator can view/update only permitted organisation settings.
- Cross-tenant access is denied and covered by tests.
- Public URLs use UUIDs for externally addressable organisations.
- Organisation bootstrap is transaction-safe and leaves no orphan records.
