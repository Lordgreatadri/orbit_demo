# Public Pages and Authentication Requirements

## Public landing page
Communicate:
- ORBIT's purpose: attendance, workforce scheduling, and operational visibility.
- Suitable audiences: educational institutions, businesses, public institutions, NGOs, and field-based teams.
- Core capabilities: QR attendance, location-aware check-in, schedules, leave, notifications, reporting.
- Benefits: reduced manual processes, clearer accountability, centralised visibility, lower dependence on specialised attendance hardware.
- Calls to action: Register your organisation, Sign in, Contact us / Request a demo.

Avoid unverified quantitative claims, fabricated customer logos, or implying that future integrations are already available.

## Public pages
- Home/landing.
- About ORBIT / How it works / User Guide.
- Contact us with validation, spam/rate protection, safe handling, and a useful confirmation.
- Terms and Conditions.
- Privacy Policy.
- Login and password reset.
- Organisation registration and onboarding.
- Appropriate 404/403/500 pages.

Legal pages should be clearly marked for owner/legal review before production; do not invent legal guarantees.

## Authentication
- Secure login/logout and session regeneration on authentication.
- Password hashing using Laravel-supported defaults.
- Password reset with expiring, single-use tokens and rate limits.
- Verification workflow if email delivery is configured.
- Generic responses where needed to avoid account enumeration.
- Secure cookie/session settings appropriate to deployment.
- Rate-limit login, registration, password reset, and invitation acceptance.
- Do not implement MFA unless included in a later approved phase; preserve a clean extension point.

## Acceptance criteria
- Visitor can navigate public pages on desktop and mobile.
- Organisation registration creates a tenant and its responsible contact/initial administrator atomically.
- Failed validation shows accessible field errors and does not leave partial tenant/account records.
- Unauthenticated users cannot access protected pages.
- Password reset is safe, rate-limited, and tested.
- No open member/student/employee registration exists.
- Theme toggle works on public and authenticated pages.
