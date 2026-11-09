# Attendance and Location Requirements

## Attendance record
Plan a model for organisation, member, attendance date/session, check-in/check-out timestamps, method, status, optional approved location, captured coordinates/accuracy when needed, verification result, and audit timestamps. Use database constraints and transactions to prevent duplicate or contradictory records.

## Office QR flow
1. Authorised administrator creates an attendance session for a tenant/location and allowed time window.
2. Backend creates a cryptographically unpredictable, signed or otherwise securely verifiable token; do not expose reusable static QR codes.
3. Token/session expires quickly. The source architecture suggests roughly 10–30 seconds for rotating QR, while the EPA screen mock-up shows 45 seconds. Make TTL configurable and choose an explicit demo default in implementation notes.
4. Authenticated member submits the token to the server.
5. Server verifies token/session, expiry, tenant, location, active session, person eligibility, time window, and duplicate check-in rules. A current code is valid for every eligible person until it expires or the screen that showed it rotates it. The same person cannot check in twice for that session. An expired, rotated, or unknown code is rejected. The raw code is not stored.
6. If location verification is enabled for the session, request browser permission and validate the received coordinates against server-side policy.
7. Record attendance atomically and return a clear result.
8. Dashboard summaries update on refresh/polling initially; do not claim push real-time updates unless implemented.
9. Creating a session notifies each active account that may record attendance, using the organisation's enabled channels. The message names the session, place, and time window, and links to My attendance at `#opened-sessions`. It does not include the rotating code. Signed-in staff see a live code on that page. An office display can still show its own code. Issuing a new code expires only the code that screen was showing.
10. The on-screen code encodes a short-lived check-in address for the signed-in person. The displayed code stays a bounded, centred size so a phone camera can see the whole code.

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
- Duplicate check-in is refused for the same session or work period. A second session on the same calendar day can still be recorded. Leave and an excused day cover that calendar day.
- The same rotating code can be used by every eligible person while it is current. Expired, rotated, and unknown codes are rejected. The same person cannot replay it for that session.
- Accepted and rejected verification decisions are stored with the attendance event, including GPS coordinates, accuracy, capture time, location, and geofence result. The raw QR code is not stored.
- A submitted beacon identifier is registered-beacon evidence. It is not described as automatic Bluetooth detection or as proof the phone is beside the beacon.
- A token cannot be used across tenants or unrelated locations.
- GPS permission denial and out-of-geofence cases produce safe, understandable outcomes.
- All attendance actions are authenticated, authorised, validated, and auditable.
- Sensitive location data is not unnecessarily exposed in lists, logs, or exports.
