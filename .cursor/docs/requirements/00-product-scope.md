# Product Scope and Priorities

## Product statement
ORBIT is a centralised workforce attendance, location-visibility, and workforce-scheduling platform for institutions. It is designed to reduce manual attendance work, improve accountability, and provide a consolidated view of office, field, and remote activity.

## Platform-wide foundation
- Multi-tenant SaaS architecture.
- Organisation public registration and initial administrator onboarding.
- Invitation/provisioning for additional members.
- Authentication, authorisation, password reset, auditability, and tenant isolation.
- Public landing and informational pages.
- Responsive admin web interface and mobile-friendly/PWA-ready member interface.
- API-ready domain logic and decoupled integrations.
- Light theme by default plus a toggleable distinctive alternate theme.

## Phase 1 — first implementation milestone
1. Repository assessment and implementation plan.
2. Public landing page.
3. Public pages: About/How It Works or User Guide, Contact, Terms and Conditions, Privacy Policy.
4. Login, logout, registration of organisation and responsible contact, password reset, and verification where configured.
5. Tenant bootstrap, initial organisation administrator, role/permission foundation.
6. Responsive application shell and empty-state dashboard.
7. Test coverage for auth, provisioning, tenant isolation, and public forms.

## EPA demo scope after foundation
P0:
- Admin dashboard with clearly identified fictional data.
- Member/employee directory and profile view.
- Attendance listing and office check-in using a time-limited QR session.
- Field check-in using explicit GPS permission and approved geofence.
- Office locations and field monitoring views.

P1:
- Shift schedules and assignments.
- Leave requests and authorised approve/reject workflow.
- Notifications.
- Basic workforce analytics.

## Explicitly out of initial demo scope
- Subscription/billing management and monetisation.
- Payroll integrations.
- Actual EPA systems or live EPA personnel data.
- BLE, NFC, Wi-Fi attendance, facial recognition, biometrics.
- AI analytics/fraud detection.
- Microservices.
- Native Flutter applications.
- Production-grade offline attendance synchronisation.
- Unspecified payment API integrations.

## Future roadmap only
Mobile applications, offline sync, advanced dynamic QR rotation, device binding, advanced geofencing, API integrations, SSO, advanced analytics, BLE, enterprise reporting, multi-region deployment, and payment/subscription capabilities require later planning and approval.

## Scope rule
Do not implement a future item merely because it appears in the long-term architecture or slides. Ask for approval if a requirement conflicts with the current phase.
