# Attendance and Location Requirements

## Attendance record
Plan a model for organisation, member, attendance date/session, check-in/check-out timestamps, method, status, optional approved location, captured coordinates/accuracy when needed, verification result, and audit timestamps. Use database constraints and transactions to prevent duplicate or contradictory records.

## Office QR flow
1. Authorised administrator creates an attendance session for a tenant/location and allowed time window.
2. Backend creates a cryptographically unpredictable, signed or otherwise securely verifiable token; do not expose reusable static QR codes.
3. Token/session expires quickly. The source architecture suggests roughly 10–30 seconds for rotating QR, while the EPA screen mock-up shows 45 seconds. Make TTL configurable and choose an explicit demo default in implementation notes.
4. Authenticated member submits the token to the server.
5. Server verifies token/session signature, expiry, tenant, location, active session, member eligibility, time window, and duplicate check-in rules.
6. If location verification is enabled for the session, request browser permission and validate the received coordinates against server-side policy.
7. Record attendance atomically and return a clear result.
8. Dashboard summaries update on refresh/polling initially; do not claim push real-time updates unless implemented.

## Field attendance flow
1. Authenticated field member explicitly starts an attendance action.
2. Browser requests geolocation permission.
3. Submit coordinates and reported accuracy with the action.
4. Server validates the assigned/approved geofence, time, member status, tenant, and any required schedule/assignment.
5. Record accepted or rejected outcome with a safe reason code.
6. Avoid continuous background location tracking in the MVP.

## Geofence
- Store approved location coordinates and radius with validated ranges.
- Calculate distance server-side using a reliable geospatial/distance method.
- Define behaviour for missing GPS, poor accuracy, outside-radius results, and denied permission.
- Do not trust a client-provided boolean such as `location_verified=true`.
- Consider GPS spoofing limitations; communicate verification as risk reduction, not absolute proof.

## Statuses
Use documented status values such as present, late, absent, on leave, checked out, pending verification, or rejected where relevant. Keep status definitions and cutoff/timezone rules consistent.

## Acceptance criteria
- Duplicate/replayed requests do not create duplicate attendance.
- Expired/invalid QR tokens are rejected.
- A token cannot be used across tenants or unrelated locations.
- GPS permission denial and out-of-geofence cases produce safe, understandable outcomes.
- All attendance actions are authenticated, authorised, validated, and auditable.
- Sensitive location data is not unnecessarily exposed in lists, logs, or exports.
