# Initial Data Model Guidance

This is a conceptual starting point, not a final migration specification. Inspect the existing schema before generating migrations. Avoid creating duplicate tables or prematurely implementing deferred features.

## Core entities
- `organizations`: tenant identity, UUID, name, type/industry, status, timezone, contact details, optional branding reference.
- `users`: identity, UUID, name, email/phone, password hash, status, verification timestamps, settings only when needed.
- `organization_user` or equivalent membership entity if one identity can belong to multiple organisations; otherwise document the simpler one-user/one-tenant model before implementation.
- `roles` / `permissions`: use a maintained permission package if justified, with explicit platform-versus-tenant boundaries.
- `departments`: organisation, name, code.
- `locations`: organisation, name, type, coordinates, geofence radius, active state.
- `attendance_sessions` / `qr_sessions`: organisation, location, token hash or safe token reference, expiry, status, creator.
- `attendance_records` / `attendance_logs`: organisation, user/member, location, method, check-in/out, status, verification metadata.
- `shifts`, `shift_assignments`: organisation, schedule times, location, member/role assignments.
- `leave_requests`: organisation, requester, type, date range, reason, status, approver, decision timestamps.
- `notifications`: recipient, type, payload/message, read/delivery state, tenant scope.
- `invitations`: organisation, invitee, role, hashed token, expiry, acceptance/revocation timestamps, inviter.
- `activity/audit logs`: actor, tenant, action, target, timestamp, minimal safe metadata.
- `contact_messages`: public contact form submissions with abuse controls and retention policy.

## Schema rules
- Numeric `id` primary key on every table.
- UUID on externally addressable domain entities, unique and indexed.
- Foreign keys and cascade/restrict behaviour chosen deliberately.
- `organization_id` on tenant-owned entities, with tenant-aware indexes.
- Unique constraints for identity and business invariants.
- UTC timestamps; display in organisation/user timezone.
- Store passwords as hashes only. Store invitation/one-time tokens as hashes where possible.
- Do not store raw QR secrets in logs. Prefer hashed/short-lived token references and server-side session state where appropriate.
- Avoid polymorphic relationships unless the use case warrants them.
- Do not implement subscriptions/payments merely because future plans mention them.
