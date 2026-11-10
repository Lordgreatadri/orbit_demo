<?php

namespace Tests\Feature\Database;

use App\Models\Activity;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\NotificationPreference;
use App\Models\NotificationReadAudit;
use App\Models\NotificationTemplate;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkArrangement;
use App\Models\WorkforceNoticeDelivery;
use App\Notifications\WorkforceMessage;
use App\Workforce\WorkforceNotifier;
use Database\Seeders\WorkforceScenarioSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataUpgradeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function leave_period_backfill_is_idempotent_and_tenant_scoped(): void
    {
        [$first, $second] = $this->organisations();
        $placeholder = $this->balance($first, 2024, '2000-01-01', '2000-12-31');
        $custom = $this->balance($first, 2026, '2026-07-01', '2027-06-30');
        $other = $this->balance($second, 2025, '2025-04-01', '2026-03-31');

        $this->artisan('orbit:data:backfill-leave-periods')
            ->expectsOutputToContain('Updated: 1')
            ->assertSuccessful();
        $this->artisan('orbit:data:backfill-leave-periods')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $this->assertSame('2024-01-01', $placeholder->refresh()->period_start->toDateString());
        $this->assertSame('2024-12-31', $placeholder->period_end->toDateString());
        $this->assertSame('2026-07-01', $custom->refresh()->period_start->toDateString());
        $this->assertSame('2025-04-01', $other->refresh()->period_start->toDateString());
        $this->assertSame(1, Activity::query()->where('event', 'leave.periods_backfilled')->count());
        $this->assertSame($first->getKey(), Activity::query()->where('event', 'leave.periods_backfilled')->value('organization_id'));
    }

    #[Test]
    public function leave_request_coverage_backfill_does_not_recalculate_a_stored_count(): void
    {
        $organization = Organization::factory()->create();
        $unset = $this->request($organization, '2026-12-01', '2026-12-03', 0, null);
        $stored = $this->request($organization, '2026-01-01', '2026-01-02', 2, ['2026-01-01', '2026-01-02']);

        $this->artisan('orbit:data:backfill-leave-requests')->assertSuccessful();
        $this->artisan('orbit:data:backfill-leave-requests')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $unset->refresh();
        $stored->refresh();
        $this->assertSame(3, $unset->day_count);
        $this->assertSame(['2026-12-01', '2026-12-02', '2026-12-03'], $unset->covered_dates);
        $this->assertSame(2, $stored->day_count);
        $this->assertSame(['2026-01-01', '2026-01-02'], $stored->covered_dates);
        $this->assertSame(1, Activity::query()->where('event', 'leave.coverage_backfilled')->count());
    }

    #[Test]
    public function workforce_backfill_links_a_legacy_request_once(): void
    {
        $organization = Organization::factory()->create();
        $request = $this->request($organization, '2026-05-04', '2026-05-04', 1, ['2026-05-04']);
        $request->forceFill(['leave_policy_id' => null])->save();

        $this->artisan('orbit:data:backfill-workforce-v1')
            ->expectsOutputToContain('Updated: 2')
            ->assertSuccessful();
        $this->artisan('orbit:data:backfill-workforce-v1')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $this->assertNotNull($request->refresh()->leave_policy_id);
        $this->assertSame(1, LeavePolicy::withoutTenantScope()->where('organization_id', $organization->getKey())->count());
        $this->assertSame(1, LeaveAdjustment::withoutTenantScope()->where('organization_id', $organization->getKey())->count());
        $this->assertSame(1, Activity::query()->where('event', 'workforce.legacy_backfilled')->count());
        $this->assertSame(1, Activity::query()->where('event', 'leave.adjusted')->count());
    }

    #[Test]
    public function shift_clock_backfill_copies_only_the_same_organisation_shift(): void
    {
        [$first, $second] = $this->organisations();
        $shift = Shift::withoutTenantScope()->create([
            'organization_id' => $first->getKey(),
            'name' => 'Day',
            'starts_at' => '08:00:00',
            'ends_at' => '17:00:00',
            'effective_from' => '2026-01-01',
            'status' => 'published',
        ]);
        Shift::withoutTenantScope()->create([
            'organization_id' => $second->getKey(),
            'name' => 'Night',
            'starts_at' => '22:00:00',
            'ends_at' => '06:00:00',
            'crosses_midnight' => true,
            'effective_from' => '2026-01-01',
            'status' => 'published',
        ]);
        $assignment = ShiftAssignment::withoutTenantScope()->create([
            'organization_id' => $first->getKey(),
            'shift_id' => $shift->getKey(),
            'staff_member_id' => StaffMember::factory()->forOrganization($first)->create()->getKey(),
            'work_date' => '2026-11-12',
        ]);

        $this->artisan('orbit:data:backfill-shift-clocks')->assertSuccessful();
        $this->artisan('orbit:data:backfill-shift-clocks')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $this->assertSame('08:00:00', substr((string) $assignment->refresh()->starts_at, 0, 8));
        $this->assertSame(1, Activity::query()->where('event', 'schedule.clocks_backfilled')->count());
    }

    #[Test]
    public function notification_read_backfill_is_idempotent_and_rejects_a_foreign_recipient(): void
    {
        [$first, $second] = $this->organisations();
        $reader = User::factory()->create(['organization_id' => $first->getKey()]);
        $notice = $this->notice($first, $reader, now());
        $this->notice($second, $reader, now());

        $this->artisan('orbit:data:backfill-notification-reads')
            ->expectsOutputToContain('Updated: 1')
            ->assertSuccessful();
        $this->artisan('orbit:data:backfill-notification-reads')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $this->assertSame(1, NotificationReadAudit::withoutTenantScope()->count());
        $this->assertSame($first->getKey(), NotificationReadAudit::withoutTenantScope()->where('notification_id', $notice)->value('organization_id'));
        $this->assertSame(1, Activity::query()->where('event', 'notifications.reads_backfilled')->count());
    }

    #[Test]
    public function a_claim_without_status_is_recorded_sent_and_is_not_sent_again(): void
    {
        [$first, $second] = $this->organisations();
        $reader = User::factory()->create(['organization_id' => $first->getKey()]);
        $legacy = WorkforceNoticeDelivery::withoutTenantScope()->create([
            'organization_id' => $first->getKey(),
            'user_id' => $reader->getKey(),
            'dedupe_key' => 'legacy-claim',
            'channel' => 'notice',
            'status' => null,
            'attempts' => 1,
        ]);
        $queued = WorkforceNoticeDelivery::withoutTenantScope()->create([
            'organization_id' => $first->getKey(),
            'user_id' => $reader->getKey(),
            'dedupe_key' => 'still-queued',
            'channel' => 'notice',
            'status' => 'queued',
            'attempts' => 1,
        ]);
        $failed = WorkforceNoticeDelivery::withoutTenantScope()->create([
            'organization_id' => $first->getKey(),
            'user_id' => $reader->getKey(),
            'dedupe_key' => 'already-failed',
            'channel' => 'notice',
            'status' => 'failed',
            'failure_kind' => 'permanent',
            'attempts' => 1,
        ]);
        $other = WorkforceNoticeDelivery::withoutTenantScope()->create([
            'organization_id' => $second->getKey(),
            'user_id' => User::factory()->create(['organization_id' => $second->getKey()])->getKey(),
            'dedupe_key' => 'other-sent',
            'channel' => 'notice',
            'status' => 'sent',
            'attempts' => 1,
        ]);

        Notification::fake();
        app(WorkforceNotifier::class)->deliver($reader, 'leave.decided', 'Open', 'Body', 'brand-new-key');
        Notification::assertSentTo($reader, WorkforceMessage::class);
        Notification::fake();
        app(WorkforceNotifier::class)->deliver($reader, 'leave.decided', 'Title', 'Body', 'legacy-claim');
        Notification::assertNothingSent();
        $this->assertNull($legacy->refresh()->status);
        $this->assertSame(1, $legacy->attempts);

        $this->artisan('orbit:data:backfill-notice-claims')
            ->expectsOutputToContain('Updated: 1')
            ->assertSuccessful();
        $this->artisan('orbit:data:backfill-notice-claims')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $this->assertSame('sent', $legacy->refresh()->status);
        $this->assertSame('queued', $queued->refresh()->status);
        $this->assertSame('failed', $failed->refresh()->status);
        $this->assertSame('sent', $other->refresh()->status);
        $this->assertSame(1, Activity::query()->where('event', 'workforce.notice_claims_recorded')->count());
        $this->assertSame($first->getKey(), Activity::query()->where('event', 'workforce.notice_claims_recorded')->value('organization_id'));
    }

    #[Test]
    public function notification_inbox_backfill_fills_only_missing_owner_fields(): void
    {
        [$first, $second] = $this->organisations();
        $reader = User::factory()->create(['organization_id' => $first->getKey()]);
        $other = User::factory()->create(['organization_id' => $second->getKey()]);
        $missing = $this->inboxRow($reader, null, null, ['event' => 'leave.decided', 'title' => 'Private title']);
        $keptEvent = $this->inboxRow($reader, null, 'schedule.published', ['event' => 'leave.decided']);
        $owned = $this->inboxRow($other, $second->getKey(), 'leave.decided', ['event' => 'attendance.recorded']);
        $unresolved = (int) DB::table('notifications')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => null,
            'event' => null,
            'type' => 'App\\Notifications\\WorkforceMessage',
            'notifiable_type' => 'App\\Models\\StaffMember',
            'notifiable_id' => $reader->getKey(),
            'data' => json_encode(['title' => 'No event']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('orbit:data:backfill-notification-inbox')
            ->expectsOutputToContain('Updated: 2')
            ->assertSuccessful();
        $this->artisan('orbit:data:backfill-notification-inbox')
            ->expectsOutputToContain('Updated: 0')
            ->assertSuccessful();

        $missing = DB::table('notifications')->where('id', $missing)->first();
        $keptEvent = DB::table('notifications')->where('id', $keptEvent)->first();
        $owned = DB::table('notifications')->where('id', $owned)->first();
        $unresolved = DB::table('notifications')->where('id', $unresolved)->first();

        $this->assertSame($first->getKey(), (int) $missing->organization_id);
        $this->assertSame('leave.decided', $missing->event);
        $this->assertSame($first->getKey(), (int) $keptEvent->organization_id);
        $this->assertSame('schedule.published', $keptEvent->event);
        $this->assertSame($second->getKey(), (int) $owned->organization_id);
        $this->assertSame('leave.decided', $owned->event);
        $this->assertNull($unresolved->organization_id);
        $this->assertNull($unresolved->event);
        $this->assertSame(1, Activity::query()->where('event', 'notifications.inbox_backfilled')->count());
        $this->assertSame($first->getKey(), Activity::query()->where('event', 'notifications.inbox_backfilled')->value('organization_id'));
    }

    #[Test]
    public function reference_rows_keep_a_database_unique_key_and_a_rerun_keeps_custom_wording(): void
    {
        $demo = Organization::factory()->create();
        $demo->forceFill(['settings' => ['demo' => true]])->save();
        $other = Organization::factory()->create();

        $this->seed(WorkforceScenarioSeeder::class);

        $annual = LeaveType::withoutTenantScope()
            ->where('organization_id', $demo->getKey())
            ->where('code', 'ANNUAL')
            ->firstOrFail();
        $annual->update(['name' => 'Rest and recovery']);
        $preference = NotificationPreference::withoutTenantScope()
            ->where('organization_id', $demo->getKey())
            ->firstOrFail();
        $preference->update(['sms' => true]);
        NotificationTemplate::withoutTenantScope()->create([
            'organization_id' => $demo->getKey(),
            'event' => 'leave.approved',
            'channel' => 'all',
            'subject' => 'Kept subject',
            'body' => 'Kept body',
        ]);

        $this->seed(WorkforceScenarioSeeder::class);

        $this->assertSame('Rest and recovery', $annual->refresh()->name);
        $this->assertSame(1, LeaveType::withoutTenantScope()->where('organization_id', $demo->getKey())->where('code', 'ANNUAL')->count());
        $this->assertTrue($preference->refresh()->sms);
        $this->assertSame('Kept body', NotificationTemplate::withoutTenantScope()->where('organization_id', $demo->getKey())->value('body'));
        $this->assertSame(0, LeaveType::withoutTenantScope()->where('organization_id', $other->getKey())->where('code', 'ANNUAL')->count());
        $this->assertSame(1, WorkArrangement::withoutTenantScope()->where('organization_id', $demo->getKey())->where('code', 'office')->count());

        try {
            LeaveType::withoutTenantScope()->create([
                'organization_id' => $demo->getKey(),
                'name' => 'Another annual',
                'code' => 'ANNUAL',
            ]);
            $this->fail('A duplicate leave type code was stored.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, LeaveType::withoutTenantScope()->where('organization_id', $demo->getKey())->where('code', 'ANNUAL')->count());
        }

        LeaveType::withoutTenantScope()->create([
            'organization_id' => $other->getKey(),
            'name' => 'Annual',
            'code' => 'ANNUAL',
        ]);
        $this->assertSame(1, LeaveType::withoutTenantScope()->where('organization_id', $other->getKey())->where('code', 'ANNUAL')->count());
    }

    /**
     * @return array{0: Organization, 1: Organization}
     */
    private function organisations(): array
    {
        return [Organization::factory()->create(), Organization::factory()->create()];
    }

    private function balance(Organization $organization, int $year, string $start, string $end): LeaveBalance
    {
        return LeaveBalance::withoutTenantScope()->create([
            'organization_id' => $organization->getKey(),
            'staff_member_id' => StaffMember::factory()->forOrganization($organization)->create()->getKey(),
            'leave_type_id' => LeaveType::withoutTenantScope()->create([
                'organization_id' => $organization->getKey(),
                'name' => 'Annual '.$year,
                'code' => 'ANN'.$year,
            ])->getKey(),
            'year' => $year,
            'period_start' => $start,
            'period_end' => $end,
            'entitled_days' => 10,
        ]);
    }

    /**
     * @param  list<string>|null  $dates
     */
    private function request(Organization $organization, string $start, string $end, int $days, ?array $dates): LeaveRequest
    {
        return LeaveRequest::withoutTenantScope()->create([
            'organization_id' => $organization->getKey(),
            'staff_member_id' => StaffMember::factory()->forOrganization($organization)->create()->getKey(),
            'leave_type_id' => LeaveType::withoutTenantScope()->create([
                'organization_id' => $organization->getKey(),
                'name' => 'Annual',
                'code' => 'ANNUAL-'.$start,
            ])->getKey(),
            'starts_on' => $start,
            'ends_on' => $end,
            'day_count' => $days,
            'covered_dates' => $dates,
            'reason' => 'Fixture',
            'status' => 'submitted',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function inboxRow(User $reader, ?int $organizationId, ?string $event, array $data): int
    {
        return (int) DB::table('notifications')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'event' => $event,
            'type' => 'App\\Notifications\\WorkforceMessage',
            'notifiable_type' => User::class,
            'notifiable_id' => $reader->getKey(),
            'data' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notice(Organization $organization, User $reader, mixed $readAt): int
    {
        return (int) DB::table('notifications')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->getKey(),
            'event' => 'schedule.published',
            'type' => 'App\\Notifications\\WorkforceMessage',
            'notifiable_type' => User::class,
            'notifiable_id' => $reader->getKey(),
            'data' => '{}',
            'read_at' => $readAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
