<?php

namespace Tests\Feature\Workforce;

use App\Contracts\PushNotifier;
use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Enums\WorkType;
use App\Jobs\Middleware\RestoreTenantContext;
use App\Jobs\Workforce\DeliverWorkforceNotice;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\LeaveAdjustment;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkArrangement;
use App\Models\WorkforceNoticeDelivery;
use App\Models\WorkforcePolicy;
use App\Notifications\WorkforceMessage;
use App\Tenancy\TenantContext;
use App\Workforce\AttendanceEngine;
use App\Workforce\LeavePlanner;
use App\Workforce\LegacyWorkforceBackfill;
use App\Workforce\ScheduleResolver;
use App\Workforce\WorkforceNotifier;
use App\Workforce\WorkforceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ScheduleLeaveReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00', 'Africa/Accra'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function a_leave_year_anchor_cannot_change_while_balances_or_requests_exist(): void
    {
        [$admin, $member, $staff] = $this->people();
        $type = $this->leaveType($admin);
        $policy = LeavePolicy::query()->where('leave_type_id', $type->getKey())->firstOrFail();

        $this->actingAs($admin)->post(route('org.leave.policies.update', $policy), $this->policyPayload(7, 1))
            ->assertSessionHas('status');
        $this->assertSame(7, (int) $policy->fresh()->year_start_month);

        $this->actingAs($admin)->post(route('org.leave.policies.update', $policy), $this->policyPayload(1, 1))
            ->assertSessionHas('status');
        $this->assertSame(1, (int) $policy->fresh()->year_start_month);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-02',
            'reason' => 'Pending day',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('org.leave.policies.update', $policy), $this->policyPayload(7, 1))
            ->assertSessionHasErrors('leave');
        $this->assertSame(1, (int) $policy->fresh()->year_start_month);

        $this->actingAs($admin)->post(route('org.leave.policies.update', $policy), $this->policyPayload(1, 1, 'line_manager'))
            ->assertSessionHas('status');
        $this->assertSame('line_manager', $policy->fresh()->approval_mode->value);

        $request = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $policy->update(['approval_mode' => 'any_approver']);
        $this->actingAs($admin)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Approved',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('org.leave.policies.update', $policy), $this->policyPayload(7, 1))
            ->assertSessionHasErrors('leave');
        $this->assertSame(1, (int) $policy->fresh()->year_start_month);

        $this->actingAs($member)->post(route('org.leave.policies.update', $policy), $this->policyPayload(7, 1))
            ->assertForbidden();
    }

    #[Test]
    public function a_balance_alone_blocks_a_leave_year_anchor_change(): void
    {
        [$admin] = $this->people();
        $type = $this->leaveType($admin);
        $policy = LeavePolicy::query()->where('leave_type_id', $type->getKey())->firstOrFail();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();

        LeaveBalance::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $type->getKey(),
            'year' => 2026,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'entitled_days' => 10,
            'used_days' => 0,
            'pending_days' => 0,
            'adjusted_days' => 0,
        ]);

        $this->actingAs($admin)->post(route('org.leave.policies.update', $policy), $this->policyPayload(7, 1))
            ->assertSessionHasErrors('leave');
        $this->assertSame(1, (int) $policy->fresh()->year_start_month);
    }

    #[Test]
    public function a_february_29_leave_year_ends_on_the_day_before_the_next_anchor(): void
    {
        $policy = new LeavePolicy(['year_start_month' => 2, 'year_start_day' => 29]);
        $planner = app(LeavePlanner::class);

        $this->assertSame(['2023-02-28', '2024-02-28'], $planner->period($policy, '2024-02-28'));
        $this->assertSame(['2024-02-29', '2025-02-27'], $planner->period($policy, '2024-02-29'));
        $this->assertSame(['2024-02-29', '2025-02-27'], $planner->period($policy, '2024-03-01'));
        $this->assertSame(['2023-02-28', '2024-02-28'], $planner->period($policy, '2023-02-28'));
        $this->assertSame(['2023-02-28', '2024-02-28'], $planner->period($policy, '2023-03-01'));

        $calendar = new LeavePolicy(['year_start_month' => 1, 'year_start_day' => 1]);
        $this->assertSame(['2023-01-01', '2023-12-31'], $planner->period($calendar, '2023-06-15'));
        $this->assertSame(['2024-01-01', '2024-12-31'], $planner->period($calendar, '2024-01-01'));
    }

    #[Test]
    public function early_check_in_uses_the_upcoming_period_and_ignores_one_that_has_ended(): void
    {
        [$admin, , $staff] = $this->people();
        $morning = $this->shift($admin, 'Morning', '08:00', '12:00');
        $afternoon = $this->shift($admin, 'Afternoon', '13:00', '17:00');
        $this->assign($admin, $morning, $staff, '2026-11-06');
        $this->assign($admin, $afternoon, $staff, '2026-11-06');

        WorkforcePolicy::resolve($admin->organization)->update(['grace_minutes' => 60]);
        $resolver = app(ScheduleResolver::class);
        $expect = [
            '11:59' => 'Morning',
            '12:00' => 'Afternoon',
            '12:01' => 'Afternoon',
            '12:55' => 'Afternoon',
            '12:59' => 'Afternoon',
            '13:00' => 'Afternoon',
            '13:01' => 'Afternoon',
        ];

        foreach ($expect as $time => $name) {
            $period = $resolver->periodAt($staff, Carbon::parse('2026-11-06 '.$time, 'Africa/Accra'));
            $this->assertNotNull($period, $time);
            $this->assertSame($name, $period->shift->name, $time);
        }

        WorkforcePolicy::resolve($admin->organization)->update(['grace_minutes' => 0]);
        $this->assertNull($resolver->periodAt($staff, Carbon::parse('2026-11-06 12:55:00', 'Africa/Accra')));
        $this->assertSame('Morning', $resolver->periodAt($staff, Carbon::parse('2026-11-06 11:59:00', 'Africa/Accra'))->shift->name);
    }

    #[Test]
    public function a_check_in_between_periods_is_not_rejected_as_a_duplicate_of_the_finished_one(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $morning = $this->shift($admin, 'Morning', '08:00', '12:00', $remote->uuid);
        $afternoon = $this->shift($admin, 'Afternoon', '13:00', '17:00', $remote->uuid);
        $this->assign($admin, $morning, $staff, '2026-11-04', $remote->uuid);
        $this->assign($admin, $afternoon, $staff, '2026-11-04', $remote->uuid);
        WorkforcePolicy::resolve($admin->organization)->update(['grace_minutes' => 60]);

        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00', 'Africa/Accra'));
        $this->checkIn($member);
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-out'))->assertSessionHas('status');

        Carbon::setTestNow(Carbon::parse('2026-11-04 12:55:00', 'Africa/Accra'));
        $this->checkIn($member);

        $records = AttendanceRecord::query()->with('shiftAssignment.shift')->where('staff_member_id', $staff->getKey())->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame('Afternoon', $records->last()->shiftAssignment->shift->name);
    }

    #[Test]
    public function the_assignment_location_rejects_a_different_site(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $office = WorkArrangement::query()->where('code', 'office')->firstOrFail();
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $flexible = WorkArrangement::query()->where('code', 'flexible')->firstOrFail();
        $siteA = Location::factory()->forOrganization($admin->organization)->create([
            'name' => 'Office A',
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'geofence_radius_m' => 200,
        ]);
        $siteB = Location::factory()->forOrganization($admin->organization)->create([
            'name' => 'Office B',
            'latitude' => 6.6885,
            'longitude' => -1.6244,
            'geofence_radius_m' => 200,
        ]);
        $shift = $this->shift($admin, 'Desk', '08:00', '16:00', $office->uuid);
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-04',
            'arrangement_uuid' => $office->uuid,
            'location_uuid' => $siteA->uuid,
        ])->assertSessionHas('status');

        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps($siteB->uuid))
            ->assertSessionHasErrors('attendance');
        $this->assertSame(0, AttendanceRecord::query()->count());

        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps($siteA->uuid))
            ->assertSessionHas('status');
        $this->assertSame($siteA->getKey(), AttendanceRecord::query()->value('location_id'));

        AttendanceRecord::query()->delete();
        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps(null))
            ->assertSessionHas('status');
        $this->assertSame($siteA->getKey(), AttendanceRecord::query()->value('location_id'));

        $other = Location::factory()->forOrganization($this->createOrganizationAdmin()->organization)->create();
        AttendanceRecord::query()->delete();
        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps($other->uuid))
            ->assertSessionHasErrors('location_uuid');

        AttendanceRecord::query()->delete();
        ShiftAssignment::query()->delete();
        $home = $this->shift($admin, 'Home', '08:00', '16:00', $remote->uuid);
        $this->assign($admin, $home, $staff, '2026-11-04', $remote->uuid);
        $this->checkIn($member);
        $this->assertContains(AttendanceRecord::query()->latest('id')->firstOrFail()->status->value, ['present', 'late']);

        AttendanceRecord::query()->delete();
        ShiftAssignment::query()->delete();
        $flex = $this->shift($admin, 'Flex', '08:00', '16:00', $flexible->uuid);
        $this->assign($admin, $flex, $staff, '2026-11-04', $flexible->uuid);
        $this->checkIn($member);
        $this->assertContains(AttendanceRecord::query()->latest('id')->firstOrFail()->status->value, ['present', 'late']);
    }

    #[Test]
    public function an_overnight_shift_can_be_checked_out_until_its_scheduled_end(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $night = $this->shift($admin, 'Night', '22:00', '06:00', $remote->uuid);
        $this->assign($admin, $night, $staff, '2026-11-04', $remote->uuid);

        Carbon::setTestNow(Carbon::parse('2026-11-04 22:00:00', 'Africa/Accra'));
        $this->checkIn($member);
        $record = AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $this->assertSame('2026-11-04', $record->work_date->toDateString());

        Carbon::setTestNow(Carbon::parse('2026-11-04 23:30:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-out'), ['record' => $record->uuid])->assertSessionHas('status');
        $this->assertNotNull($record->fresh()->check_out_at);
        $this->assertSame('2026-11-04', $record->fresh()->work_date->toDateString());
        $this->assertGreaterThan(0, $record->fresh()->early_leave_minutes);

        $record->refresh()->forceFill(['check_out_at' => null, 'early_leave_minutes' => 0, 'status' => AttendanceStatus::Present])->save();

        foreach (['2026-11-05 01:00:00', '2026-11-05 05:59:00'] as $time) {
            Carbon::setTestNow(Carbon::parse($time, 'Africa/Accra'));
            $this->actingAs($member)->get(route('org.attendance.mine'))->assertOk()->assertSee('Check out')->assertSee('Overnight from');
            $this->actingAs($member)->post(route('org.attendance.check-out'), ['record' => $record->uuid])->assertSessionHas('status');
            $this->assertSame('2026-11-04', $record->fresh()->work_date->toDateString());
            $record->refresh()->forceFill(['check_out_at' => null, 'early_leave_minutes' => 0, 'status' => AttendanceStatus::Present])->save();
        }

        Carbon::setTestNow(Carbon::parse('2026-11-05 02:00:00', 'Africa/Accra'));
        $this->assertSame(0, app(AttendanceEngine::class)->closeOpenRecords());
        $this->assertSame('present', $record->fresh()->status->value);

        Carbon::setTestNow(Carbon::parse('2026-11-05 06:30:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-out'), ['record' => $record->uuid])->assertSessionHas('status');
        $this->assertSame(0, (int) $record->fresh()->early_leave_minutes);
        $this->assertSame('2026-11-04', $record->fresh()->work_date->toDateString());

        $record->refresh()->forceFill(['check_out_at' => null, 'status' => AttendanceStatus::Present])->save();
        Carbon::setTestNow(Carbon::parse('2026-11-05 07:00:00', 'Africa/Accra'));
        $this->assertSame(1, app(AttendanceEngine::class)->closeOpenRecords());
        $this->assertSame('incomplete', $record->fresh()->status->value);
    }

    #[Test]
    public function a_direct_check_in_after_midnight_keeps_the_overnight_work_date(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $night = $this->shift($admin, 'Night', '22:00', '06:00', $remote->uuid);
        $this->assign($admin, $night, $staff, '2026-11-04', $remote->uuid);

        Carbon::setTestNow(Carbon::parse('2026-11-05 01:00:00', 'Africa/Accra'));
        $this->checkIn($member);
        $record = AttendanceRecord::query()->firstOrFail();
        $this->assertSame('2026-11-04', $record->work_date->toDateString());
        $this->assertSame($night->assignments()->value('id'), $record->shift_assignment_id);
    }

    #[Test]
    public function checkout_punctuality_uses_the_record_assignment_when_the_next_period_has_started(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $morning = $this->shift($admin, 'Morning', '09:00', '12:00', $remote->uuid);
        $afternoon = $this->shift($admin, 'Afternoon', '12:00', '17:00', $remote->uuid);
        $this->assign($admin, $morning, $staff, '2026-11-04', $remote->uuid);
        $this->assign($admin, $afternoon, $staff, '2026-11-04', $remote->uuid);

        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00:00', 'Africa/Accra'));
        $this->checkIn($member);
        Carbon::setTestNow(Carbon::parse('2026-11-04 11:59:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-out'))->assertSessionHas('status');
        $morningRecord = AttendanceRecord::query()->orderBy('id')->firstOrFail();
        $this->assertSame(1, (int) $morningRecord->early_leave_minutes);

        $morningRecord->forceFill(['check_out_at' => null, 'early_leave_minutes' => null])->save();
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-out'), ['record' => $morningRecord->uuid])->assertSessionHas('status');
        $this->assertSame(0, (int) $morningRecord->fresh()->early_leave_minutes);

        $this->checkIn($member);
        Carbon::setTestNow(Carbon::parse('2026-11-04 17:00:00', 'Africa/Accra'));
        $afternoonRecord = AttendanceRecord::query()->orderByDesc('id')->firstOrFail();
        $this->actingAs($member)->post(route('org.attendance.check-out'), ['record' => $afternoonRecord->uuid])->assertSessionHas('status');
        $this->assertSame(0, (int) $afternoonRecord->fresh()->early_leave_minutes);
        $this->assertSame('Afternoon', $afternoonRecord->load('shiftAssignment.shift')->shiftAssignment->shift->name);
    }

    #[Test]
    public function removing_an_assignment_rechecks_attendance_inside_the_staff_lock(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $shift = $this->shift($admin, 'Day', '08:00', '16:00', $remote->uuid);
        $assignment = $this->assign($admin, $shift, $staff, '2026-11-04', $remote->uuid);
        $this->checkIn($member);

        $this->actingAs($admin)->delete(route('org.schedules.assignments.destroy', $assignment))
            ->assertSessionHasErrors('schedule');
        $this->assertNotNull(ShiftAssignment::query()->find($assignment->getKey()));

        $future = $this->assign($admin, $shift, $staff, '2026-11-20', $remote->uuid);
        $this->actingAs($admin)->delete(route('org.schedules.assignments.destroy', $future))
            ->assertSessionHas('status');
        $this->assertNull(ShiftAssignment::query()->find($future->getKey()));

        $legacyShift = $this->shift($admin, 'Legacy day', '08:00', '16:00', $remote->uuid);
        $legacy = $this->assign($admin, $legacyShift, $staff, '2026-11-18', $remote->uuid);
        AttendanceRecord::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'work_date' => '2026-11-18',
            'period_key' => AttendanceRecord::dayPeriod('2026-11-18'),
            'check_in_at' => Carbon::parse('2026-11-18 08:05:00', 'Africa/Accra'),
            'status' => AttendanceStatus::Present,
            'source' => 'manual',
        ]);
        $this->actingAs($admin)->delete(route('org.schedules.assignments.destroy', $legacy))
            ->assertSessionHasErrors('schedule');
    }

    #[Test]
    public function adherence_counts_only_the_assignment_a_record_covers(): void
    {
        [$admin, , $staff] = $this->people();
        $morning = $this->shift($admin, 'Morning', '08:00', '12:00');
        $afternoon = $this->shift($admin, 'Afternoon', '13:00', '17:00');
        $first = $this->assign($admin, $morning, $staff, '2026-11-06');
        $this->assign($admin, $afternoon, $staff, '2026-11-06');
        AttendanceRecord::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'shift_assignment_id' => $first->getKey(),
            'work_date' => '2026-11-06',
            'period_key' => AttendanceRecord::shiftPeriod((int) $first->getKey()),
            'check_in_at' => Carbon::parse('2026-11-06 08:05:00', 'Africa/Accra'),
            'status' => AttendanceStatus::Present,
            'source' => 'gps',
        ]);

        $report = app(WorkforceReport::class)->scheduleAdherence(
            $admin->organization,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
        );
        $this->assertSame(2, $report['scheduled']);
        $this->assertSame(1, $report['attended']);

        $session = AttendanceSession::query()->create([
            'organization_id' => $admin->organization_id,
            'created_by' => $admin->getKey(),
            'name' => 'Roll call',
            'starts_at' => Carbon::parse('2026-11-07 08:00:00', 'Africa/Accra'),
            'ends_at' => Carbon::parse('2026-11-07 09:00:00', 'Africa/Accra'),
            'status' => SessionStatus::Closed,
            'qr_ttl_seconds' => 30,
            'require_location' => false,
        ]);
        $solo = $this->assign($admin, $morning, $staff, '2026-11-07');
        AttendanceRecord::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'attendance_session_id' => $session->getKey(),
            'work_date' => '2026-11-07',
            'period_key' => AttendanceRecord::sessionPeriod((int) $session->getKey()),
            'check_in_at' => Carbon::parse('2026-11-07 08:05:00', 'Africa/Accra'),
            'status' => AttendanceStatus::Present,
            'source' => 'qr',
        ]);
        $withSession = app(WorkforceReport::class)->scheduleAdherence(
            $admin->organization,
            Carbon::parse('2026-11-07'),
            Carbon::parse('2026-11-07'),
        );
        $this->assertSame(1, $withSession['scheduled']);
        $this->assertSame(0, $withSession['attended']);
        $this->assertNotNull($solo->getKey());
    }

    #[Test]
    public function arrangement_totals_ignore_draft_shifts(): void
    {
        [$admin, , $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $office = WorkArrangement::query()->where('code', 'office')->firstOrFail();

        $this->actingAs($admin)->post(route('org.schedules.store'), [
            'name' => 'Draft day',
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'effective_from' => '2026-11-01',
            'arrangement_uuid' => $office->uuid,
        ])->assertSessionHas('status');
        $draft = Shift::query()->where('name', 'Draft day')->firstOrFail();
        $this->assign($admin, $draft, $staff, '2026-11-06', $office->uuid);

        $published = $this->shift($admin, 'Published day', '08:00', '16:00', $office->uuid);
        $this->assign($admin, $published, $staff, '2026-11-07', $office->uuid);

        $mix = app(WorkforceReport::class)->arrangementMix(
            $admin->organization,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
        );
        $this->assertSame(1, $mix['office'] ?? 0);
    }

    #[Test]
    public function the_approval_queue_follows_the_policy_approval_mode(): void
    {
        [$admin, $member, $staff] = $this->people();
        $manager = $this->createMember($admin->organization, 'organization_manager');
        $managerStaff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create(['first_name' => 'Line', 'last_name' => 'Manager']);
        $other = $this->createMember($admin->organization, 'organization_manager');
        $otherStaff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($other)->create(['first_name' => 'Other', 'last_name' => 'Approver']);
        $staff->update(['first_name' => 'Kojo', 'last_name' => 'Requestor', 'reports_to_id' => $managerStaff->getKey()]);

        $type = $this->leaveType($admin);
        $policy = LeavePolicy::query()->where('leave_type_id', $type->getKey())->firstOrFail();
        $policy->update(['approval_mode' => 'line_manager']);
        $this->requestLeave($member, $type, '2026-12-08');

        $this->actingAs($manager)->get(route('org.leave.index'))->assertOk()->assertSee('Kojo Requestor');
        $this->actingAs($other)->get(route('org.leave.index'))->assertOk()->assertDontSee('Kojo Requestor');
        $request = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $this->actingAs($other)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'No',
        ])->assertSessionHasErrors('leave');
        $this->actingAs($member)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Mine',
        ])->assertForbidden();

        $staff->update(['reports_to_id' => null]);
        $this->actingAs($other)->get(route('org.leave.index'))->assertOk()->assertSee('Kojo Requestor');
        $staff->update(['reports_to_id' => $managerStaff->getKey()]);

        $department = Department::factory()->forOrganization($admin->organization)->create([
            'name' => 'Operations',
            'head_staff_member_id' => $otherStaff->getKey(),
        ]);
        $staff->update(['department_id' => $department->getKey()]);
        $policy->update(['approval_mode' => 'department_head']);
        $this->actingAs($other)->get(route('org.leave.index'))->assertOk()->assertSee('Kojo Requestor');
        $managerQueue = $this->actingAs($manager)->get(route('org.leave.index'))->assertOk()->getContent();
        $queue = Str::between($managerQueue, 'id="leave-queue-heading"', 'id="leave-cancellable-heading"');
        $this->assertStringNotContainsString('Kojo Requestor', $queue);

        $this->actingAs($admin)->get(route('org.leave.index'))->assertOk()->assertSee('Kojo Requestor');
        $department->update(['head_staff_member_id' => null]);
        $this->actingAs($manager)->get(route('org.leave.index'))->assertOk()->assertSee('Kojo Requestor');
        $this->actingAs($other)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Head is unset',
        ])->assertSessionHas('status');
    }

    #[Test]
    public function an_authorised_approver_can_find_and_cancel_another_persons_approved_leave(): void
    {
        [$admin, $member, $staff] = $this->people();
        $manager = $this->createMember($admin->organization, 'organization_manager');
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create();
        $staff->update(['first_name' => 'Abena', 'last_name' => 'Owusu']);
        $type = $this->leaveType($admin);
        $this->requestLeave($member, $type, '2026-12-09');
        $request = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();

        $this->actingAs($manager)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Yes',
        ])->assertSessionHas('status');

        $this->actingAs($manager)->get(route('org.leave.index'))
            ->assertOk()
            ->assertSee('Approved leave you can cancel')
            ->assertSee('Abena Owusu');
        $this->actingAs($member)->get(route('org.leave.index'))
            ->assertOk()
            ->assertDontSee('Approved leave you can cancel');

        $this->actingAs($member)->post(route('org.leave.cancel', $request), [
            'decision_note' => 'No',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('org.leave.cancel', $request), [
            'decision_note' => 'Plans changed',
        ])->assertSessionHas('status');

        $this->assertSame('cancelled', $request->fresh()->status->value);
        $this->assertSame(0, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('used_days'));
        $this->assertSame(0, AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->where('source', 'leave')->count());

        $this->actingAs($manager)->post(route('org.leave.cancel', $request), [
            'decision_note' => 'Again',
        ])->assertSessionHasErrors('leave');
        $this->assertSame(0, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('used_days'));
    }

    #[Test]
    public function legacy_leave_is_reserved_and_cancels_against_its_original_year(): void
    {
        [$admin, $member, $staff] = $this->people();
        $type = $this->leaveType($admin, 'Sick', 'sick');
        $this->requestLeave($member, $type, '2026-12-04');
        $request = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $days = $request->dayCount();
        $request->forceFill(['leave_policy_id' => null])->save();
        LeaveBalance::query()->where('staff_member_id', $staff->getKey())->update(['pending_days' => 0]);

        LegacyWorkforceBackfill::leaveAccounts();
        LegacyWorkforceBackfill::leaveAccounts();

        $request->refresh();
        $policy = LeavePolicy::query()->findOrFail($request->leave_policy_id);
        $this->assertSame(1, (int) $policy->year_start_month);
        $this->assertSame(1, (int) $policy->year_start_day);
        $this->assertSame($days, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('pending_days'));

        LeavePolicy::query()->create([
            'organization_id' => $admin->organization_id,
            'leave_type_id' => $type->getKey(),
            'name' => 'Later July year',
            'year_start_month' => 7,
            'year_start_day' => 1,
            'count_mode' => 'calendar',
            'weekend_days' => [6, 7],
            'exclude_non_working' => true,
            'approval_mode' => 'any_approver',
            'allow_self_approval' => false,
            'effective_from' => '2026-07-01',
            'is_active' => true,
        ]);
        $this->assertSame($policy->getKey(), $request->fresh()->leave_policy_id);

        $policy->update(['approval_mode' => 'any_approver']);
        $request->forceFill(['leave_policy_id' => null])->save();
        $this->actingAs($admin)->post(route('org.leave.decide', $request), [
            'decision' => 'approve',
            'decision_note' => 'Legacy',
        ])->assertSessionHas('status');
        $this->assertSame($days, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('used_days'));
        $this->assertSame(1, LeaveBalance::query()->where('staff_member_id', $staff->getKey())->count());

        $this->actingAs($admin)->post(route('org.leave.cancel', $request), [
            'decision_note' => 'Restored',
        ])->assertSessionHas('status');
        $this->assertSame(0, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('used_days'));
        $this->assertSame(0, AttendanceRecord::query()->where('source', 'leave')->count());
    }

    #[Test]
    public function a_legacy_shortfall_is_an_adjustment_and_the_entitlement_stays_put(): void
    {
        [$admin, $member, $staff] = $this->people();
        $type = $this->leaveType($admin, 'Study', 'study');
        $this->requestLeave($member, $type, '2026-12-11');
        $request = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $request->forceFill(['leave_policy_id' => null, 'day_count' => 5])->save();

        $balance = LeaveBalance::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $balance->forceFill([
            'entitled_days' => 20,
            'used_days' => 18,
            'pending_days' => 0,
            'adjusted_days' => 0,
        ])->save();

        LegacyWorkforceBackfill::leaveAccounts();
        LegacyWorkforceBackfill::leaveAccounts();

        $balance->refresh();
        $this->assertSame(20, (int) $balance->entitled_days);
        $this->assertSame(5, (int) $balance->pending_days);
        $this->assertSame(3, (int) $balance->adjusted_days);
        $this->assertSame(0, $balance->available());

        $adjustment = LeaveAdjustment::query()->where('leave_balance_id', $balance->getKey())->sole();
        $this->assertSame(3, (int) $adjustment->days);
        $this->assertNull($adjustment->adjusted_by);
        $this->assertSame(LegacyWorkforceBackfill::LEGACY_EXCESS_REASON, $adjustment->reason);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'leave.adjusted',
            'subject_id' => $adjustment->getKey(),
        ]);
    }

    #[Test]
    public function legacy_attendance_links_only_an_unambiguous_assignment(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $shift = $this->shift($admin, 'Day', '08:00', '16:00', $remote->uuid);
        $assignment = $this->assign($admin, $shift, $staff, '2026-11-10', $remote->uuid);
        $record = AttendanceRecord::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'work_date' => '2026-11-10',
            'period_key' => AttendanceRecord::dayPeriod('2026-11-10'),
            'check_in_at' => Carbon::parse('2026-11-10 08:05:00', 'Africa/Accra'),
            'status' => AttendanceStatus::Present,
            'source' => 'gps',
        ]);

        LegacyWorkforceBackfill::attendanceLinks();
        $record->refresh();
        $this->assertSame($assignment->getKey(), $record->shift_assignment_id);
        $this->assertSame(AttendanceRecord::shiftPeriod((int) $assignment->getKey()), $record->period_key);

        Carbon::setTestNow(Carbon::parse('2026-11-10 08:10:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps(null))->assertSessionHasErrors('attendance');
        $this->assertSame(1, AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->count());
        $this->actingAs($admin)->delete(route('org.schedules.assignments.destroy', $assignment))->assertSessionHasErrors('schedule');

        $gap = AttendanceRecord::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'work_date' => '2026-11-11',
            'period_key' => AttendanceRecord::dayPeriod('2026-11-11'),
            'check_in_at' => Carbon::parse('2026-11-11 12:30:00', 'Africa/Accra'),
            'status' => AttendanceStatus::Present,
            'source' => 'gps',
        ]);
        $morning = $this->shift($admin, 'Split morning', '08:00', '12:00', $remote->uuid);
        $afternoon = $this->shift($admin, 'Split afternoon', '13:00', '17:00', $remote->uuid);
        $this->assign($admin, $morning, $staff, '2026-11-11', $remote->uuid);
        $this->assign($admin, $afternoon, $staff, '2026-11-11', $remote->uuid);
        LegacyWorkforceBackfill::attendanceLinks();
        $this->assertNull($gap->fresh()->shift_assignment_id);

        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps(null))->assertSessionHasErrors('attendance');
        $this->assertNull($gap->fresh()->shift_assignment_id);
    }

    #[Test]
    public function a_workforce_notice_is_delivered_once_per_recipient_and_key(): void
    {
        [$admin] = $this->people();
        Notification::fake();
        $notifier = app(WorkforceNotifier::class);
        $notifier->deliver($admin, 'leave.decided', 'Leave approved', 'Approved.', 'leave-decided-9-approved');
        $notifier->deliver($admin, 'leave.decided', 'Leave approved', 'Approved.', 'leave-decided-9-approved');
        Notification::assertSentToTimes($admin, WorkforceMessage::class, 1);

        $notifier->deliver($admin, 'leave.decided', 'Leave cancelled', 'Cancelled.', 'leave-decided-9-cancelled');
        Notification::assertSentToTimes($admin, WorkforceMessage::class, 2);
        $this->assertSame(2, WorkforceNoticeDelivery::query()->where('user_id', $admin->getKey())->where('channel', 'notice')->count());

        $push = new class implements PushNotifier
        {
            public int $calls = 0;

            public function send(User $user, string $title, string $body): void
            {
                $this->calls++;
                if ($this->calls === 1) {
                    throw new RuntimeException('timeout');
                }
            }
        };
        $this->app->instance(PushNotifier::class, $push);
        NotificationPreference::query()->create([
            'organization_id' => $admin->organization_id,
            'event' => 'leave.decided',
            'mail' => false,
            'sms' => false,
            'database' => true,
            'push' => true,
        ]);

        $retry = app(WorkforceNotifier::class);
        try {
            $retry->deliver($admin, 'leave.decided', 'Push', 'Retry me.', 'leave-decided-9-push');
            $this->fail('The first push attempt should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('timeout', $exception->getMessage());
        }
        $retry->deliver($admin, 'leave.decided', 'Push', 'Retry me.', 'leave-decided-9-push');
        $this->assertSame(2, $push->calls);
        $this->assertSame(1, WorkforceNoticeDelivery::query()->where('dedupe_key', 'leave-decided-9-push')->where('channel', 'push')->count());
        Notification::assertSentToTimes($admin, WorkforceMessage::class, 3);
    }

    #[Test]
    public function a_queued_notice_restores_the_tenant_that_was_current_before_it_ran(): void
    {
        $home = Organization::factory()->create();
        $other = Organization::factory()->create();
        $recipient = $this->createMember($other);
        $context = app(TenantContext::class);
        Notification::fake();

        $context->set($home);
        $notice = new DeliverWorkforceNotice(
            (int) $other->getKey(),
            (int) $recipient->getKey(),
            'leave.decided',
            'Hello',
            'From the other organisation.',
            'tenant-restore-1',
        );
        $middleware = new RestoreTenantContext;
        $middleware->handle($notice, function () use ($notice): void {
            app()->call([$notice, 'handle']);
        });
        $this->assertSame((int) $home->getKey(), (int) $context->id());
        $this->assertSame(1, WorkforceNoticeDelivery::withoutTenantScope()->where('organization_id', $other->getKey())->count());

        $roundTrip = unserialize(serialize(clone $notice));
        $this->assertSame((int) $other->getKey(), (int) $roundTrip->organizationId);

        DeliverWorkforceNotice::dispatchSync(
            (int) $other->getKey(),
            (int) $recipient->getKey(),
            'leave.decided',
            'Hello again',
            'Queued on the sync connection.',
            'tenant-restore-queue',
        );
        $this->assertSame((int) $home->getKey(), (int) $context->id());
        $this->assertSame(1, WorkforceNoticeDelivery::withoutTenantScope()->where('dedupe_key', 'tenant-restore-queue')->count());

        $middleware = new RestoreTenantContext;
        $job = new \stdClass;
        $job->organizationId = (int) $other->getKey();

        try {
            $middleware->handle($job, function () use ($context, $other): void {
                $this->assertSame((int) $other->getKey(), (int) $context->id());
                throw new RuntimeException('fail');
            });
            $this->fail('The job should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('fail', $exception->getMessage());
        }
        $this->assertSame((int) $home->getKey(), (int) $context->id());

        $context->clear();
        $middleware->handle($job, function () use ($context, $other): void {
            $this->assertSame((int) $other->getKey(), (int) $context->id());
        });
        $this->assertNull($context->organization());
    }

    /**
     * @return array{0: User, 1: User, 2: StaffMember}
     */
    private function people(): array
    {
        $admin = $this->createOrganizationAdmin();
        $admin->organization->update(['timezone' => 'Africa/Accra']);
        $member = $this->createMember($admin->organization);
        $staff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($member)->create([
            'work_type' => WorkType::Office,
        ]);

        return [$admin, $member, $staff];
    }

    private function leaveType(User $admin, string $name = 'Annual', string $code = 'annual'): LeaveType
    {
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => $name,
            'code' => $code,
            'requires_balance' => '1',
            'default_days' => '10',
        ])->assertSessionHas('status');

        return LeaveType::query()->where('code', strtoupper($code))->firstOrFail();
    }

    private function requestLeave(User $member, LeaveType $type, string $day): void
    {
        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => $day,
            'ends_on' => $day,
            'reason' => 'A day away',
        ])->assertSessionHas('status');
    }

    /**
     * @return array<string, mixed>
     */
    private function policyPayload(int $month, int $day, string $mode = 'any_approver'): array
    {
        return [
            'year_start_month' => $month,
            'year_start_day' => $day,
            'count_mode' => 'calendar',
            'weekend_days' => [6, 7],
            'exclude_non_working' => '1',
            'approval_mode' => $mode,
            'allow_self_approval' => '0',
        ];
    }

    private function shift(User $admin, string $name, string $starts, string $ends, ?string $arrangement = null): Shift
    {
        $payload = [
            'name' => $name,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'effective_from' => '2026-11-01',
        ];

        if ($arrangement !== null) {
            $payload['arrangement_uuid'] = $arrangement;
        }

        $this->actingAs($admin)->post(route('org.schedules.store'), $payload)->assertSessionHas('status');
        $shift = Shift::query()->where('name', $name)->firstOrFail();
        $this->actingAs($admin)->post(route('org.schedules.publish', $shift))->assertSessionHas('status');

        return $shift->fresh();
    }

    private function assign(User $admin, Shift $shift, StaffMember $staff, string $date, ?string $arrangement = null): ShiftAssignment
    {
        $payload = ['staff_uuid' => $staff->uuid, 'work_date' => $date];

        if ($arrangement !== null) {
            $payload['arrangement_uuid'] = $arrangement;
        }

        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), $payload)->assertSessionHas('status');

        return ShiftAssignment::query()->where('shift_id', $shift->getKey())->where('staff_member_id', $staff->getKey())->orderByDesc('id')->firstOrFail();
    }

    private function checkIn(User $member): void
    {
        $this->actingAs($member)->post(route('org.attendance.check-in'), $this->gps(null))->assertSessionHas('status');
    }

    /**
     * @return array<string, mixed>
     */
    private function gps(?string $locationUuid): array
    {
        return array_filter([
            'methods' => ['gps'],
            'latitude' => 5.6037,
            'longitude' => -0.1870,
            'accuracy_m' => 20,
            'captured_at' => now()->toIso8601String(),
            'location_uuid' => $locationUuid,
        ], fn ($value) => $value !== null);
    }
}
