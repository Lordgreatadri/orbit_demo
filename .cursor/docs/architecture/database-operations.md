# Database operations

ORBIT separates schema, defaults, and existing business data.

```text
Migration
    schema, indexes, foreign keys, unique keys, and a check constraint

Seeder
    idempotent platform or tenant defaults

Data upgrade
    an explicit command that changes existing business rows

Database constraint
    the integrity guarantee if application code races or is wrong
```

`php artisan migrate` changes structure. `php artisan orbit:data:...` changes existing business rows. A seeder may create a missing default. It must not replace wording, a leave policy, a work arrangement, a theme, or a communication choice an administrator has saved.

## When to use which

Use a migration for a table, column, index, foreign key, unique key, or supported check constraint. A migration may copy or set a value only when the new constraint cannot be created until that value exists, and there is no safe point to stop between the two. Say so in the migration comment and name the command that repeats the fill.

Use a seeder for platform reference data and for tenant defaults created with the organisation or a demo directory. Look the row up by its full identity, including `organization_id` when the row belongs to an organisation. `firstOrCreate` leaves an existing row alone. `updateOrCreate` is for a small set of platform records whose stored attributes must match the current definition, such as a system role flag. `upsert` is for a large deterministic batch where model events are not required. Do not use either to overwrite tenant configuration.

Use `php artisan orbit:data:...` when existing rows must be recalculated, linked, or backfilled and the schema can exist without that rewrite. The command is idempotent, chunked, and limited to each row's own organisation. It prints `Processed`, `Updated`, `Skipped`, and `Errors`. It does not print personal data. A material change records one activity row per organisation with a count, using the existing audit log. The read-audit table itself is the record of who read a notice.

## Commands

| Order | Command | Changes |
| --- | --- | --- |
| 1 | `orbit:data:backfill-notice-claims` | A delivery claim whose `status` is null is recorded `sent`. Queued, sent, and failed claims stay as they are. Null is already treated as finished by the sender, so this command does not send the notice. Run it after `2026_11_10_140400`. |
| 2 | `orbit:data:backfill-notification-inbox` | A null `organization_id` is copied from that notice's user. A null `event` is copied from that notice's JSON payload. A stored value is not replaced. A notice with no user, or no event in the payload, stays null. Run it after `2026_11_10_140500`. Until it runs, the organisation inbox hides a notice whose organisation is null. |
| 3 | `orbit:data:backfill-leave-periods` | Balance rows whose period is null or still `2000-01-01` / `2000-12-31`. The year on that row supplies the dates. A chosen period is not replaced. Run it before `2026_11_09_121150` can add the period unique key on a populated table. |
| 4 | `orbit:data:backfill-leave-requests` | Requests whose `day_count` is 0 and `covered_dates` is null. A stored count is not recalculated. Run it after `2026_11_09_121300`. |
| 5 | `orbit:data:backfill-workforce-v1` | `LegacyWorkforceBackfill`: calendar-year policy link, pending-day reconciliation, and an unambiguous attendance link. It walks rows with `each()` in chunks of 1000. `Updated` counts policy links, attendance links, new adjustments, and balances that were created or whose pending or adjusted days changed. A balance that was only read is not counted. Run it after the leave-policy and attendance-link columns exist. A second run does not add another excess adjustment once the balance already covers the shortfall. |
| 6 | `orbit:data:backfill-shift-clocks` | Assignments with no clock, copied from the shift in the same organisation. Run it after `2026_11_09_180000`. |
| 7 | `orbit:data:backfill-notification-reads` | One `notification_read_audits` row for a notice that was already read, when the recipient still belongs to that organisation. Run it after `2026_11_10_140600` and after the inbox command, so a historical notice has an organisation before the audit is copied. |

Each command is idempotent, restartable, and safe to run twice. It changes only the rows named above. It chunks the work (200 rows, or 1000 for the workforce command's existing walker). It does not copy one organisation's value onto another. A material change writes one activity row per organisation with a count and no personal data. A second run that updates nothing writes no new activity.

Running a command twice does not insert a second adjustment, audit, or historical link. Unique keys remain in the database. `updateOrCreate` and `firstOrCreate` do not replace those keys.

## Fresh install, existing install, rerun, failure, rollback

A fresh install runs the schema migrations, then seeders when an operator asks for them. The data-upgrade commands find nothing to change unless older rows were loaded first.

An existing database that already applied `2026_11_09_120000_schedule_leave_policy_foundation` keeps those tables. The split files from `2026_11_09_120100` through `2026_11_09_121300` see the tables and columns and do not create them again. The rollback rule for that case is in the next section.

`2026_11_09_121100` adds the period unique key immediately when `leave_balances` is empty. When the table already has rows, the columns are added nullable and `2026_11_09_121150` stops until `orbit:data:backfill-leave-periods` has filled them. Migrate again after the command.

A failed chunk is not committed where the upgrade uses a transaction. Run the command again. Rows already given their final value are skipped.

Deploy the sender and `2026_11_10_140400` together. The column is nullable and has no `queued` default. A claim written before the column existed stays null. The sender treats null as a finished claim, so those notices are not sent again while the command has not yet run. The command then records null as `sent`. Do not add the column with a default of `queued` and fill it later: anything that is not `sent` or a permanent failure is eligible to be sent.

`2026_11_10_140500` adds nullable `organization_id` and `event` and does not copy existing rows. New notices store both values when they are created. Historical notices stay out of the organisation inbox until `orbit:data:backfill-notification-inbox`. The columns are not made required. A notice can outlive its user, and the payload may not contain an event.

## Migration history compatibility / rollback

`2026_11_09_120000_schedule_leave_policy_foundation` was one migration. It created several schedule and leave tables and also rewrote existing leave rows. That file was applied on the local development database, then removed from the repository and replaced by `2026_11_09_120100` through `2026_11_09_121300`. The compatibility layer exists so a database that already recorded the combined migration is not asked to create the same tables again, while a new database still gets one migration per table.

On a fresh database the combined file is absent, so only the split migrations run. Migrating forward is safe. Rolling that batch back in reverse timestamp order is also safe: each `down()` drops the tables and columns that file created, and there is no older copy of the data.

On a database that already ran the combined migration, migrating forward is safe. Each split file checks for the table, column, or index and leaves it in place, then Laravel records the new filename. The business rows stay where they are. Run the data-upgrade commands for any fill the old file used to perform.

Do not run `migrate:rollback` on the split batch in that database. The split `down()` methods drop the same tables. They cannot tell that those tables were created by the combined migration. Rollback would delete schedule and leave data that the new filenames did not create.

A future engineer should handle it this way:

- Do not put the combined migration back beside the split files. A fresh database would run the old rewrite again, or try to create tables the split files also create.
- Do not turn the split `down()` methods into no-ops. A fresh database needs a real rollback.
- On a database that already had the combined migration, take a backup before any rollback. Prefer a forward repair. Removing the split filenames from the `migrations` table without dropping tables is a manual repair after that backup, not `migrate:rollback`.
- Do not rewrite the released October foundation migrations, `2026_11_06_090000_align_notifications_table`, or `2026_11_06_120000_align_attendance_periods_and_shared_qr_tokens`.

- Never hide a material transformation of existing business data inside a migration when the transformation can be independently executed, observed, audited, retried, and verified as an explicit data-upgrade operation.

The same caution applies to `2026_11_10_090000`. The local development database recorded `2026_11_10_090000_schedule_leave_legacy_backfill`. The repository file is now `2026_11_10_090000_create_workforce_notice_deliveries`. It only creates `workforce_notice_deliveries` when the table is missing. The next migrate on that local database records the new name and does not create the table again. `down()` of the new name drops the table. Do not roll it back there.

### Evidence that `2026_11_10_090000` was not released to production

- The repository contains `database/migrations/2026_11_10_090000_create_workforce_notice_deliveries.php`. The historical filename is not a file in `database/migrations`.
- `.cursor/docs/implementation/phase-1-progress.md` records a local `php artisan migrate` that applied `2026_11_10_090000_schedule_leave_legacy_backfill` and says that database will not re-run it. The same log records local applies of `120100`–`121300` and `140100`–`140600`. Those notes describe the local development database.
- `.cursor/docs/implementation/stage-0-repository-assessment.md` says production database credentials are unknown and were not invented. The development default is SQLite.
- `.cursor/docs/implementation/dns-and-tls.md` says no production DNS, certificate, or proxy settings were changed.
- No document in `.cursor/docs` records a production deploy of this migration.

The structural rename is therefore the current file. The historical name stays in this note and in the migration comment. It is not restored as a second migration.

## Release class

| Class | Meaning | Treatment |
| --- | --- | --- |
| A | Not released to production | Safe to restructure |
| B | Treat the file as released history | Do not rewrite it |
| C | Applied on a local database only | May be split if a database that already ran the old file does not create the same table twice |

`2026_11_09_120000_schedule_leave_policy_foundation` was class C. It is replaced by one file per table. The October foundation migrations, the attendance period-key migration, and the notification id alignment are class B and were not rewritten.

## Inventory of writes inside migrations

Migrations that only create or alter structure, with no row rewrite, are omitted.

| Migration | Operation | Classification | Action |
| --- | --- | --- | --- |
| `2026_10_30_100000_add_metadata_to_roles_and_permissions_tables` | Sets `is_system` and platform scope on rows that existed when the columns were added | Required schema bootstrap | Keep. Class B |
| `2026_11_06_090000_align_notifications_table` | Rebuilds a legacy UUID primary key and copies each notice | Structural repair | Keep. Class B. `down()` is intentionally empty |
| `2026_11_06_120000_align_attendance_periods_and_shared_qr_tokens` | Sets `period_key` before the column becomes required and unique | Required schema bootstrap | Keep. Class B. The unique key is invalid while the column is null |
| `2026_11_09_120000_schedule_leave_policy_foundation` | Created several tables and rewrote balances, requests, and leave accounts | Historical transformation | Removed. Class C. Replaced by `120100`–`121300` plus the data commands |
| `2026_11_09_121100` / `121150` | Adds period columns. The unique key waits while any period is null | Required schema bootstrap | Keep the wait. The fill is the leave-period command |
| `2026_11_09_180000_schedule_leave_integrity` | Adds assignment clocks, the attendance link, a leave index, and the balance check on PostgreSQL and MySQL | Structural, plus a former clock copy | Clock copy moved to `orbit:data:backfill-shift-clocks`. Class A |
| `2026_11_10_090000_create_workforce_notice_deliveries` | Creates `workforce_notice_deliveries` | Structural | No row rewrite. Class A. Historical filename `2026_11_10_090000_schedule_leave_legacy_backfill` was local only |
| `2026_11_10_140400_add_delivery_state_to_workforce_notice_deliveries` | Adds nullable `status` with no default | Structural | The `sent` update was removed. `orbit:data:backfill-notice-claims` records a null claim as sent. Null is not resent |
| `2026_11_10_140500_add_organization_and_event_to_notifications` | Adds nullable `organization_id` and `event` | Structural | The row copy was removed. `orbit:data:backfill-notification-inbox` fills what it can. The columns stay nullable |
| `2026_11_10_140600_create_notification_read_audits_table` | Creates the read-audit table | Structural | Historical copy moved to `orbit:data:backfill-notification-reads` |

## Seed ownership

Platform roles and permissions come from `RolesAndPermissionsSeeder`. It updates system rows from configuration and does not edit a role an administrator created (`is_system` false). Countries are reference data and use `updateOrCreate` on the country name.

`WorkforceScenarioSeeder` runs only for an organisation flagged demo. Leave types, notification channel rows, arrangements, and entitlement rules use `firstOrCreate` on the organisation plus the business key. A renamed leave type, an SMS choice, and a saved notification template stay as the administrator left them. The seeder does not write `notification_templates`. An educational demo policy is moved to a July working-day year only while it is still the untouched January calendar default.

`ProvisionOrganizationWorkspace` creates the primary domain and branding with `firstOrCreate` when the organisation is created. That is tenant bootstrap, not a migration.

The database unique keys are the final duplicate protection: leave type and work arrangement on organisation plus code, notification preference on organisation plus event, notification template on organisation plus event plus channel, delivery claim on user plus dedupe key plus channel, and read audit on `notification_read_once`. A seeder lookup does not replace those constraints.
