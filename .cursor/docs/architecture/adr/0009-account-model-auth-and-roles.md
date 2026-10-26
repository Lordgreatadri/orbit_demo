# ADR-0009: Account model, authentication implementation, and role foundation

- Status: Accepted for Phase 1 (proposed by engineering; confirm with product owner)
- Date: 2026-10-24
- Relates to: ADR-0002, ADR-0003, ADR-0005

## Context
The overview (§5) requires a decision on whether users are global identities or tenant-specific accounts
before the schema is implemented. The repository was greenfield (Laravel 13 skeleton), so no existing auth
scaffolding constrained the choice. Laravel 13 no longer ships Breeze; the official options are React/Vue/
Livewire starter kits (Fortify-backed) or hand-written controllers on framework primitives.

## Decisions

### 1. One user belongs to exactly one organisation (or to the platform)
- `users.organization_id` is a nullable foreign key. `NULL` means a **platform** account (super admin, support).
- `users.email` is **globally unique** (lower-cased before validation and storage).
- A person who needs access to two organisations uses two accounts. A membership pivot
  (`organization_user`) is **not** introduced now; adding it later is a contained migration because all
  tenant checks go through `User::organization_id` / `TenantContext`.

### 2. Tenant context is derived, never supplied
- `App\Tenancy\TenantContext` (request-scoped singleton) is set by the `SetTenantContext` middleware from
  the authenticated user and cleared after each request and before/after each queued job.
- `App\Models\Concerns\BelongsToOrganization` adds a global scope (`organization_id = current`) and fills
  `organization_id` on create. `platform_super_admin` may open `/app/members`, which lists every organisation's members and writes `platform.members.viewed`. `platform_support` cannot. Other tenant rows stay closed to platform accounts.
- Policies additionally compare `organization_id` so that a missing scope cannot silently widen access.

### 3. Identifiers
- Every table keeps a numeric `id`. `organizations`, `users`, `invitations`, `contact_messages` carry a
  `uuid` (ordered UUID, unique). `HasPublicUuid` builds on the framework `HasUuids` trait but targets the
  secondary column, keeps `id` auto-incrementing, and uses `uuid` as the route key (malformed UUIDs → 404).

### 4. Authentication implementation
- Session guard, explicit small controllers in `App\Http\Controllers\Auth` (login, logout, registration,
  password reset, email verification, password confirmation) built on `Auth::attempt`, `RateLimiter`,
  the `Password` broker, `MustVerifyEmail`, and signed URLs. No third-party auth package in Phase 1.
- Throttles: login 5/min per email+IP (lockout with retry-after), registration 5/min per IP,
  password-reset request 3/min per IP, verification resend 6/min, invitation accept 10/min, contact 5/min.
- Email verification is enabled by `ORBIT_REQUIRE_EMAIL_VERIFICATION` (default `true`). Verifying the
  initial administrator's email transitions the organisation from `pending_verification` to `active`.
- Phone is captured (`users.phone`, `phone_verified_at`). Workspace routes use
  `identity.verified`, which keeps the account on one verification screen until
  both e-mail and phone are confirmed. The phone code is a 6-digit value stored
  as an HMAC in cache for 10 minutes. Delivery is `App\Contracts\SmsSender`,
  bound to `LogSmsSender` until a provider adapter is approved (ADR-0007).
  The code is written to the application log and is not returned in the HTTP response.
- MFA extension point: the login controller delegates to a single `LoginRequest::authenticate()`; a later
  phase can introduce Fortify or a second-factor step without changing routes or views.

### 5. Roles and permissions
- `spatie/laravel-permission` with the default `web` guard and **teams disabled**. Because an account has one
  tenant, role names are global and tenant isolation does not depend on role scoping.
- Tenant roles (config `orbit.roles.tenant`): `organization_admin`, `organization_manager`, `member`.
  Platform roles (`orbit.roles.platform`): `platform_super_admin`, `platform_support`.
- `User::assignRole()` is guarded: a platform role can only be assigned to a user with
  `organization_id = NULL`, and tenant roles only to tenant users. Violations throw and are tested.
- Permissions are seeded idempotently (`RolesAndPermissionsSeeder`). Phase 1 permissions:
  `organization.view`, `organization.update`, `members.view`, `members.invite`, `members.manage`,
  `platform.access`.

### 6. Provisioning paths
- `POST /register` is the only public account-creation endpoint and always creates an organisation plus its
  responsible contact as `organization_admin`, atomically.
- `invitations` (tenant-owned, `token_hash` SHA-256 of a 64-char random token, 7-day expiry, single-use,
  revocable, bound to e-mail and role) are the only other account-creation path.
- Terms/privacy consent is recorded on the user (`terms_accepted_at`, `terms_version`).

## Consequences
- Simple, testable isolation model; cross-tenant tests are straightforward.
- Users who belong to several organisations need separate accounts until a membership model is approved.
- Auth code is visible in the repository and must be maintained by the team; the surface is small.
- Adding teams-mode or a tenancy package later would require a new ADR and migration plan.
