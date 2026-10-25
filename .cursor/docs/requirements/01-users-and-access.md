# Users, Roles, and Access Requirements

## User categories
- Platform Super Administrator: platform-level configuration and explicitly authorised support functions.
- Platform Support: limited support access with audit trails; no implicit unrestricted tenant access.
- Organisation Administrator: manages their own organisation's settings and authorised members.
- Organisation Manager/Approver: permissions assigned for defined operational workflows.
- Member: employee, staff member, student, lecturer, or field officer, depending on the tenant's institution type and configured use case.
- Public visitor: access to public information and contact form only.

Role names must be configurable or permission-based where possible; do not hard-code EPA-specific role labels into core business rules.

## Registration
- Public registration creates an organisation and its responsible contact/initial organisation administrator.
- Validate organisation name, industry/type, contact identity, email/phone as required, and password.
- Prevent duplicate organisation/account creation through appropriate validation and database constraints.
- Apply throttling, verification, abuse protection, and clear terms/privacy consent records as required.
- Do not offer public self-registration for members, students, employees, or staff.

## Invitations
- Only authorised organisation administrators can invite users into their tenant.
- Invitations are tied to tenant, intended email/phone, role/permissions, expiry, and single-use token.
- Invite acceptance must verify token validity, expiry, revocation state, tenant, and intended recipient.
- Role assignment must be restricted to roles the inviter is permitted to grant.
- Record invitation creation, acceptance, expiry, revocation, and relevant actor in audit history.

## Authorization
- Every protected route/action requires server-side authentication and authorization.
- Tenant users can access only tenant records they are authorised to see.
- Platform-wide actions require separate platform permissions.
- Test cross-tenant access attempts, including UUID guessing, exports, relationship updates, and direct API calls.
