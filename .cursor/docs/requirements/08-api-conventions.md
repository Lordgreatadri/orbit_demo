# API Conventions (for when API endpoints are implemented)

## Versioning and response
- Prefix public API routes with `/api/v1`.
- Use JSON, standard HTTP status codes, stable UUID public identifiers, and documented pagination/filtering.
- Use Laravel Form Requests and API Resources.
- Keep response envelopes consistent within the project. A possible convention is `{ "success": true, "message": "...", "data": ... }`; validation and authorization failures should follow a consistent documented error format.
- Do not expose stack traces, SQL errors, internal IDs, secrets, or tenant-existence information to unauthorised callers.

## Security
- Authenticate every private endpoint and authorise each operation.
- Apply rate limits and input size limits.
- Enforce tenant context from the authenticated principal, never from untrusted payload alone.
- Document scopes/token lifecycle if Sanctum/API tokens are enabled.
- Add feature tests for unauthenticated, forbidden, invalid, cross-tenant, and successful requests.

## Initial future resource map
Potential endpoints once their modules are implemented:
- `POST /api/v1/auth/...`
- `GET /api/v1/users`, `GET /api/v1/users/{uuid}`
- `POST /api/v1/attendance/check-in`, `POST /api/v1/attendance/check-out`
- `GET /api/v1/attendance`
- `GET /api/v1/locations`, `GET /api/v1/locations/{uuid}`
- `GET /api/v1/schedules`
- `GET /api/v1/leave-requests`, `POST /api/v1/leave-requests`
- `GET /api/v1/analytics/dashboard`

These are planning examples, not a requirement to implement the entire API in Phase 1. Prefer consistent resource naming and avoid exposing sequential IDs.
