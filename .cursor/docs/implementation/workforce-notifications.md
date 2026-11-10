# Workforce notifications

Accepted design: ADR-0015. Requirements: `requirements/10-workforce-notifications.md`.

## Flow

Leave, schedule, and attendance finish their own transaction. `WorkforceNotifier::send()` renders the template, then `DeliverWorkforceNotice::dispatch(...)->afterCommit()`. The job's `organizationId` is restored by `IsTenantAware` before `deliver()` runs. The job sends nothing when the loaded user belongs to a different organisation.

A provider exception does not roll back the business row. On `QUEUE_CONNECTION=sync` the job reports the exception and returns, because that driver cannot wait and try again. On the database queue the same exception is rethrown for the worker.

## Tables

| Table | Role |
| --- | --- |
| `communication_policies` | One row per organisation. Absent until an administrator saves it. Defaults are all enabled. |
| `user_notification_preferences` | Unique on user and category. Optional opt-out only. |
| `notification_templates` | Unique on organisation, event, and channel. Channel `all` is the only one written. |
| `workforce_notice_deliveries` | Unique on user, dedupe key, and channel. `status` is `queued`, `sent`, `failed`, or null. Null is a claim stored before the column existed. The sender does not send it again. |
| `notifications` | User inbox (`notifiable` is the user, not the staff row). `organization_id` and `event` are stamped when the row is created. `read_at` null means unread. `uuid` is the public id. |
| `notification_read_audits` | One row per organisation, notice, and user. Written only when `read_at` changes from null to a timestamp. |

Each table has its own migration under `2026_11_10_140100` through `2026_11_10_140600`: communication policies, user preferences, templates, delivery state, inbox columns, and read audits. Creating a table is skipped when that table is already present, so an install that applied an earlier combined file does not create it twice. `2026_11_10_140400` adds nullable `status` and does not update existing claims. The sender treats a null status as a finished claim, so a deploy does not send those notices again. `php artisan orbit:data:backfill-notice-claims` then records that null as `sent`. `2026_11_10_140500` adds nullable `organization_id` and `event` and does not copy existing rows. `php artisan orbit:data:backfill-notification-inbox` fills a missing organisation from that notice's user and a missing event from that notice's payload. Until that command runs, the organisation inbox hides a notice whose organisation is still null. The columns stay nullable. `2026_11_06_090000_align_notifications_table` rebuilds an old UUID-primary-key inbox with nullable `organization_id` and `event` and without the foreign key. A database that already has the numeric inbox receives the foreign key from `140500` when the column is missing. `php artisan orbit:data:backfill-notification-reads` copies notices that were already read into the audit table once. The unique key ignores a second copy.

Indexes: `notifications_owner_unread` on recipient and `read_at`, `notifications_owner_event` on recipient and `event`, and `workforce_notice_status_index` on organisation and status.

## Channels and providers

`WorkforceMessage::via()` returns `database` and `mail` from `NotificationPreference::channelsFor()`. SMS and push are outside that call. SMS uses `SmsTransmitter` and the same dedupe key. Push uses the bound `PushNotifier`, which is still `NullPushNotifier`. Selecting an SMS vendor remains ADR-0012 configuration (`sms:sync-providers` and the provider row). No second SMS interface was added.

Email uses `MailMessage`: subject, greeting, body, an organisation line, a salutation with the organisation name, and an action button when a URL exists. The shared mail view is unchanged, so invitation mail stays as it was. Tenant branding in this milestone is the organisation name plus a host built by `Origin::url`, not a per-notice colour or logo.

## Queue, retry, and idempotency

`DeliverWorkforceNotice` sets `tries = 3`, `timeout = 30`, and `backoff()` of 30, 120, and 600 seconds. `config/queue.php` keeps `retry_after` at 90 seconds for the database connection. That value must stay above the 30 second timeout. A lower `DB_QUEUE_RETRY_AFTER` can reserve the same running job twice.

Failed attempts land in `failed_jobs` (`QUEUE_FAILED_DRIVER=database-uuids`). Inspect with `php artisan queue:failed` and retry with `php artisan queue:retry`. Do not retry a delivery whose `failure_kind` is `permanent` or `invalid_configuration`; the claim already blocks it. Temporary, rate-limited, and unknown failures delete the claim and use the job attempts.

The `notice` channel wraps database and mail in one `notify()`. A throw after the in-app insert removes the claim, so a retry can write a second in-app row and send a second email. Mail has no provider idempotency key. SMS will not insert a second `sms_messages` row for the same key. An invalid phone never inserts that row and is stored as `failed` / `permanent`. Provider HTTP status, timeouts, and the SMS job's own retries stay in `DeliverSmsMessages`. The workforce row is `sent` once `transmit()` has handed the message over.

Dedupe keys are listed in ADR-0015. They are truncated to 80 characters. They are not timestamps. The field-alert key adds the organisation's calendar date because the same activity can be quiet again on a later day.

## Reminders

`routes/console.php` schedules `workforce:remind` daily at 17:00 and `workforce:field-status` hourly. The remind command calls:

- `remindDueShifts` — tomorrow in the organisation timezone, published shift, `reminded_at` claimed before send. Skipped when schedule notices or attendance reminders are off. Opting out still consumes `reminded_at`.
- `remindMissedAttendance` — yesterday, published, no attendance record that date, no approved leave covering that date. The employee and a different line manager in the same organisation.
- `remindPendingLeave` — still submitted, `created_at` at least 2 days ago, same approver query as submit.
- `remindExpiringBalances` — `period_end` within 14 days and at least one day available.

Each pass is chunked. A second run the same day queues jobs that find an existing `sent` claim and return. Low balance is not on this schedule. It runs after an approval or an adjustment when `available()` is 2 or less. The key includes the available number, so a later drop can send once more.

## Inbox, bell, and read audit

`Inbox::unreadCount()` is a `COUNT` filtered by the recipient user, the organisation, and `read_at is null`. It does not select `data`. `Inbox::page()` paginates 15. `Inbox::recent()` takes five rows for the dashboard and for the header bell. The bell is a button with the unread count in its accessible name. It opens a dialog of those five notices. Each item posts to `org.notifications.open`, which sets `read_at` on the first read, writes one `notification_read_audits` row, and redirects to the stored action URL when `safeUrl` accepts it. Otherwise it returns to the current page. Mark one and mark all on the centre use the same transition. Mark all reads unread ids in chunks of 200 and inserts the audits in one statement per chunk. A notice that already has `read_at` is left out of that insert.

`Inbox::find()` is the lookup and 404s for any other user or organisation. A posted `user_id`, `recipient_id`, or `organization_id` is not read. `safeUrl` rejects a userinfo URL and any host other than the organisation's primary host. A relative path must start with `/org/`. The destination route applies its own authorisation. A path that does not exist is the framework's not-found response, not an error in the inbox.

`workforce_notice_deliveries.status` is not updated when the user reads the inbox row.

Routes, all under the tenant host: `org.notifications.index`, `update`, `preferences`, `policy`, `template`, `read-all`, `open`, and `read`. Writes are throttled to 60 per minute. The layout loads the unread count and the five recent rows once. The dashboard runs the count again for its card. Both counts are the indexed count, not a full history. There is no public API. A later student user is the same `User` notifiable. Workforce modules resolve the user from the staff profile before they call the notifier.

## Audit and secrets

`Audit::log` records policy flags, channel booleans, category booleans, and the template event plus channel. Template bodies are not in the audit properties. Provider credentials stay in the SMS provider configuration and are not copied onto notices or deliveries.

## Future mobile boundary

Do not add `/api/v1` for this milestone. A later client can list `Inbox::page()`, read `Inbox::unreadCount()`, mark `Inbox::find()` / `markAllRead()`, and save preferences through the same actions the web form uses. Action URLs are already application paths on the tenant host. Deep-link scheme and push tokens are not part of this implementation.

## Production queue

`QUEUE_CONNECTION=database` stores jobs in the `jobs` table and does not run them by itself. Production needs both of these processes, supervised so they restart after a deploy or a crash:

```text
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=30 --max-time=3600
php artisan schedule:work
```

`--tries=3` matches the notice job. `--timeout=30` matches it and stays under `DB_QUEUE_RETRY_AFTER` (90). `--max-time=3600` lets the process exit cleanly once an hour so the supervisor starts a new one. After a deploy, run `php artisan queue:restart` so workers finish the current job and reload code. Do not kill a worker mid-job unless the claim can be retried.

Watch `failed_jobs` and the application log. A growing `jobs` table means the worker is stopped or slower than the enqueue rate. SMS provider failures are on `sms_messages` and `sms_message_events`, not only on `failed_jobs`, because that pipeline records the outcome itself.

The PHPUnit configuration sets `QUEUE_CONNECTION=sync`. A green test suite does not mean a worker is running. Local development with the database driver also needs `php artisan queue:work`, or mail and SMS wait in `jobs`.
