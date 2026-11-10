# Workforce notifications

Accepted design: ADR-0015. Schedule, leave, and attendance remain the owners of their business rules. This requirement is the communication capability those modules already call.

## Who a notice belongs to

A notice belongs to an organisation user. The staff profile is how leave, schedule, and attendance find that user. Student sign-in is not part of this product yet. When a student is an organisation user, the same inbox, preferences, and read audit apply. No second notification system is required. The browser never chooses the recipient.

## Unread, read, and delivery

Unread means `read_at` is empty. Read means `read_at` is a timestamp. The screen shows the words Unread and Read as well as weight and a badge. Delivery is separate: a channel can be queued, sent, or failed whether or not the person has opened the in-app notice.

The first time a notice becomes read, ORBIT stores one audit row for that organisation, notice, and user, including when it was read. Opening it again does not add a row. Mark all as read writes an audit only for notices that were unread.

## Capability and policy

ORBIT provides the inbox, mail, SMS handoff, recipient resolution, templates, queueing, retry, dedupe, and delivery state. The organisation decides which categories and channels are on. A person may opt out of optional categories only. A person cannot opt out of a mandatory operational notice. The organisation can still turn that category or channel off for everyone.

SMS is sent only when the organisation has enabled SMS for that event and the platform has an enabled active provider. The default event row leaves SMS off. The policy `sms_enabled` switch is a master off, not a second place that turns SMS on.

## What a person can do

- Use the header bell to see the unread count and the five newest notices, open one, and land on its action page when the stored link is safe. View all opens the notification centre.
- Open Notifications and see the newest page of 15, filtered by All, Leave, Schedule, Attendance, or Announcements, with the title, summary, and date.
- See unread and read as text, not by colour alone, and mark one or all as read. The first read is audited.
- Follow an action link that stays on this organisation's host or a relative `/org/` path. Opening the notice does not grant access the destination would otherwise refuse.
- See the unread count in the header and on the dashboard, and the five newest notices on the dashboard.
- Turn optional category channels off. Mandatory events ignore that choice.
- An administrator with `notifications.manage` can save the organisation policy, the per-event channel matrix, and a subject/body template. Blanking both template fields restores the default wording.

A person cannot read, mark, or change another person's notices, another organisation's policy, or another organisation's template. A posted user or organisation id is ignored.

## Notices that are sent

| Event | Who receives it | When |
| --- | --- | --- |
| Leave submitted, amended, withdrawn | Approvers for that request, not the requester | After the leave transaction commits |
| Leave approved, not approved, or cancelled | The requesting employee | After the leave transaction commits |
| Leave balance low | That employee | After approval or an adjustment, when 2 days or fewer remain |
| Leave still waiting | Those approvers | Daily, for a request still submitted after 2 days |
| Leave balance expiring | That employee | Daily, when the period ends within 14 days and at least 1 day remains |
| Schedule assigned or removed | That staff member | After the schedule action |
| Upcoming shift | That staff member | Daily, for tomorrow's published assignment that has not been reminded |
| Attendance recorded or late | That staff member | After a recorded check-in |
| Attendance not recorded | That staff member | After their own check-in is refused for geofence, leave, device, manual, location, or method |
| Missing check-out | That staff member | When an open record is marked incomplete |
| Missed attendance | That staff member and their line manager in the same organisation | Daily, for yesterday's published shift with no attendance record and no approved leave covering that date |
| Session opened | Active users in that organisation with `attendance.record` | When a session is opened |
| Field assignment or quiet field activity | That assignee | On assignment, or on the hourly field-status run |

A failed leave, schedule, or attendance transaction does not send a notice. Publishing a shift with nobody assigned does not send a notice. Editing a shift template does not send a notice, because assigned periods keep the clocks they were given. Token expiry, a duplicate tap, and an OTP failure do not send a notice.

## Channels

- In-app: stored on `notifications`, scoped to the recipient and the organisation.
- Email: the existing application mail layout. The subject is the notice title. The body names the organisation. A plain-text alternative comes from Laravel's mail message.
- SMS: the platform transmitter in ADR-0012. The body is the same short sentence, cut at 1000 characters. Vendor HTTP calls stay in the SMS job.

Push is not delivered. WhatsApp is not implemented. There is no public notification API. A later client should use the inbox and preference services rather than a provider.

## Content

Messages name the person, the leave type or shift, and the dates. They do not include internal ids, decision notes, passwords, tokens, one-time codes, or provider secrets. Links are ordinary signed-in application routes.

## Isolation

Every policy, preference, template, delivery claim, and inbox row is tied to one organisation. Recipient lookup stays inside that organisation. A user from another organisation receives nothing and cannot address these records.
