# ADR-0013: Attendance engine and verification methods

- Status: Accepted
- Date: 2026-11-04

## Context

Workforce attendance, QR sessions, GPS, BLE, Wi-Fi, schedules, leave, notifications, and reporting were reserved routes. The product must serve more than the fictional EPA tenant. Facial recognition, fingerprint biometrics, NFC, payroll, and fraud scoring stay out until a later legal and product decision.

## Decision

One attendance engine records the outcome of a check-in. Verification methods are separate decisions combined by that engine.

- Dynamic QR, GPS geofence, and registered beacon evidence can confirm attendance. Beacon evidence is an identifier the organisation registered for a device installed at a site. The web app does not scan Bluetooth and does not treat that identifier as proof of proximity.
- Wi-Fi can support another method and cannot confirm attendance alone. Browsers cannot read a BSSID; a device client submits it to the same endpoint.
- One-time codes are off until the organisation enables them.
- Device binding is an optional extra check.
- Remote and hybrid staff may check in without a geofence when the organisation allows it.
- Face, fingerprint, and NFC are named methods that the engine always refuses. No biometric or NFC capture exists.
- Push notification delivery is a null plug. Mail, the database inbox, and platform SMS are the live channels.
- Reports count stored rows. They are not produced by a fraud or prediction model.

QR tokens are random, stored as hashes, and replaced on each rotation. A current code can be used by every eligible person until it expires or that screen rotates it. The same person cannot check in twice for the same session. One-time codes for a single person stay single-use. GPS coordinates, accuracy, capture time, location, and geofence result are evidence for one attendance action, not a continuous track. Accepted and rejected verification decisions are stored on the attendance event trail. A person can have more than one attendance record on a calendar day when the records belong to different sessions or work periods. Leave and an excused day still cover that calendar day.

## Amendment

- Date: 2026-11-06
- A rotating QR code is not globally single-use. Replay protection is per person and session.
- Duplicate check-in is per session or work period. The calendar-day unique key was replaced so a later shift does not collide with an earlier session.
- The web beacon flow is named beacon evidence. Automatic BLE detection remains a future client and does not need a second attendance engine.

## Consequences

- A later method implements the same decision shape and does not rewrite the engine.
- EPA remains one fictional tenant. Policies, not seed data, hold working hours and code lifetime.
