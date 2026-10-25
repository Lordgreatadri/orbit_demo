# EPA Ghana Demonstration Tenant Requirements

## Purpose
Demonstrate how ORBIT could provide the Environmental Protection Agency, Ghana, with a unified view of office attendance, field activity, schedules, leave, and basic workforce analytics. This is a demo MVP, not a live EPA deployment.

## Demo data rules
- Use fictional names and clearly labelled sample data.
- Do not integrate with EPA systems or use actual staff information.
- Seed a demo tenant named “Environmental Protection Agency — Ghana”.
- Example locations from the source: EPA Head Office — Accra, EPA Tema Office, EPA Kumasi Office, EPA Takoradi Office, and Field Operations.
- Example departments: Administration, Finance, Human Resources, Operations, Environmental Monitoring, Field Services, IT, and Legal.
- Demo KPI values are illustrative only. Label them as sample data in seed/demo mode and never imply they are live.

## Admin navigation
Dashboard, Employees/Members, Attendance, Locations, Field Monitoring, Schedules, Leave, Analytics, Notifications, Settings. Render only modules implemented and authorised; use planned empty states for later modules rather than dead links.

## Dashboard
- KPI cards: total members, present, late, absent, on leave, office, field, and remote where demo data supports those categories.
- Attendance by location with normal/attention/critical indicators and drill-down.
- Ghana map or a simpler location visualisation with office locations and field activity; if no map provider/configuration exists, use a truthful fallback rather than a fake live map.
- Recent activity feed with fictional names and times.
- Responsive, readable, government-ready, data-focused UI.

## Member directory/profile
Directory columns: employee/member ID, name, department, work type, location, status, last activity. Support search, filters, pagination, and tenant-safe profile detail. Use fictional seeded profiles.

## Attendance and field monitoring
- Attendance filters: date, location, department, member, status, work type.
- Office workflow: login → check in → scan active office QR → verify session/location if required → record.
- Field workflow: login → start field attendance → request GPS → server geofence verification → record.
- Field monitoring lists active/delayed/offline states only when their definitions/data are implemented. Do not simulate live tracking without clear demo labelling.

## Schedules and leave (P1)
- Display shifts, schedules, assignments, and locations.
- Members submit leave requests with type, date range, and reason.
- Authorised approvers approve/reject; record actor, decision time, and safe decision notes.
- Prevent invalid date ranges and unauthorised decisions.

## Notifications and analytics (P1)
- Notifications for attendance confirmation, shift reminders, and leave outcomes.
- Basic analytics: attendance rate, office/field attendance, late attendance. Define calculation denominator and date range; label seeded/demo metrics.

## Demo accounts
The source lists example email addresses (`admin@orbit-demo.com`, `employee@orbit-demo.com`, `field@orbit-demo.com`) but does not define secure credentials. Create local-only seeded accounts with documented setup instructions and generated/local passwords. Never embed default passwords in production or commit real credentials.
