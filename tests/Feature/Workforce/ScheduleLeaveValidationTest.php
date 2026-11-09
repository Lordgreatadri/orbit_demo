<?php

namespace Tests\Feature\Workforce;

use App\Enums\WorkType;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlementRule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffCategory;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WorkArrangement;
use App\Workforce\LeavePlanner;
use App\Workforce\ScheduleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleLeaveValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 08:05:00', 'Africa/Accra'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function viewing_schedules_does_not_create_work_arrangements(): void
    {
        [$admin] = $this->people();
        WorkArrangement::query()->delete();

        $this->actingAs($admin)->get(route('org.schedules.index'))->assertOk();
        $this->assertSame(0, WorkArrangement::query()->count());

        $this->actingAs($admin)->post(route('org.schedules.arrangements'))->assertSessionHas('status');
        $this->assertSame(4, WorkArrangement::query()->count());
        $this->actingAs($admin)->post(route('org.schedules.arrangements'))->assertSessionHas('status');
        $this->assertSame(4, WorkArrangement::query()->count());
    }

    #[Test]
    public function adjacent_periods_are_kept_and_an_overlapping_boundary_is_refused(): void
    {
        [$admin, , $staff] = $this->people();
        $morning = $this->shift($admin, 'Morning', '09:00', '12:00');
        $afternoon = $this->shift($admin, 'Afternoon', '12:00', '17:00');
        $overlap = $this->shift($admin, 'Overlap', '09:00', '13:00');

        $this->actingAs($admin)->post(route('org.schedules.assign', $morning), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $afternoon), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');
        $this->assertSame(2, ShiftAssignment::query()->count());

        $this->actingAs($admin)->post(route('org.schedules.assign', $overlap), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHasErrors('schedule');
        $this->assertSame(2, ShiftAssignment::query()->count());
    }

    #[Test]
    public function a_cross_midnight_period_blocks_the_following_morning_and_keeps_its_clock(): void
    {
        [$admin, , $staff] = $this->people();
        $night = $this->shift($admin, 'Night', '22:00', '06:00');
        $early = $this->shift($admin, 'Early', '05:00', '09:00');
        $day = $this->shift($admin, 'Day', '08:00', '12:00');

        $this->actingAs($admin)->post(route('org.schedules.assign', $night), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $early), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-07',
        ])->assertSessionHasErrors('schedule');
        $this->actingAs($admin)->post(route('org.schedules.assign', $day), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-07',
        ])->assertSessionHas('status');

        $assignment = ShiftAssignment::query()->where('shift_id', $night->getKey())->firstOrFail();
        $night->update(['starts_at' => '18:00:00', 'ends_at' => '02:00:00']);
        $bounds = app(ScheduleResolver::class)->bounds(
            '2026-11-06',
            $night->fresh(),
            'Africa/Accra',
            $assignment->fresh(),
        );

        $this->assertSame('22:00', $bounds[0]->format('H:i'));
        $this->assertSame('2026-11-07 06:00', $bounds[1]->format('Y-m-d H:i'));
    }

    #[Test]
    public function a_past_assignment_cannot_be_removed(): void
    {
        [$admin, , $staff] = $this->people();
        $shift = $this->shift($admin, 'Earlier', '08:00', '12:00');
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-02',
        ])->assertSessionHas('status');
        $assignment = ShiftAssignment::query()->firstOrFail();

        $this->actingAs($admin)->delete(route('org.schedules.assignments.destroy', $assignment))
            ->assertSessionHasErrors('schedule');
        $this->assertNotNull($assignment->fresh());
    }

    #[Test]
    public function two_published_periods_can_each_record_attendance(): void
    {
        [$admin, $member, $staff] = $this->people();
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $morning = $this->shift($admin, 'Morning', '08:00', '12:00', $remote->uuid);
        $afternoon = $this->shift($admin, 'Afternoon', '13:00', '17:00', $remote->uuid);

        foreach ([$morning, $afternoon] as $shift) {
            $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
                'staff_uuid' => $staff->uuid,
                'work_date' => '2026-11-04',
                'arrangement_uuid' => $remote->uuid,
            ])->assertSessionHas('status');
        }

        $this->actingAs($member)->post(route('org.attendance.check-in'), [
            'methods' => ['gps'],
            'latitude' => 5.6,
            'longitude' => -0.18,
            'accuracy_m' => 20,
            'captured_at' => now()->toIso8601String(),
        ])->assertSessionHas('status');

        Carbon::setTestNow(Carbon::parse('2026-11-04 14:05:00', 'Africa/Accra'));
        $this->actingAs($member)->post(route('org.attendance.check-in'), [
            'methods' => ['gps'],
            'latitude' => 5.6,
            'longitude' => -0.18,
            'accuracy_m' => 20,
            'captured_at' => now()->toIso8601String(),
        ])->assertSessionHas('status');

        $records = AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertNotSame($records[0]->period_key, $records[1]->period_key);
        $this->assertNotNull($records[0]->shift_assignment_id);
        $this->assertNotSame($records[0]->shift_assignment_id, $records[1]->shift_assignment_id);
    }

    #[Test]
    public function entitlement_rules_resolve_by_priority_then_id(): void
    {
        [$admin, $member, $staff] = $this->people();
        $finance = Department::factory()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Finance',
        ]);
        $financeCategory = StaffCategory::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Finance',
            'code' => 'FIN',
        ]);
        $staff->update([
            'department_id' => $finance->getKey(),
            'staff_category_id' => $financeCategory->getKey(),
            'grade' => 'Manager',
            'started_on' => '2010-01-01',
        ]);

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ]);
        $type = LeaveType::query()->firstOrFail();
        $policy = $type->policies()->firstOrFail();

        $rules = [
            [100, 15, []],
            [40, 20, ['department_id' => $finance->getKey()]],
            [30, 26, ['grade' => 'Manager']],
            [20, 28, ['staff_category_id' => $financeCategory->getKey(), 'grade' => 'Manager']],
            [10, 30, ['grade' => 'Manager', 'min_service_months' => 120]],
        ];

        foreach ($rules as [$priority, $days, $match]) {
            LeaveEntitlementRule::query()->create([
                'organization_id' => $admin->organization_id,
                'leave_policy_id' => $policy->getKey(),
                'priority' => $priority,
                'entitled_days' => $days,
                'effective_from' => '2026-01-01',
                ...$match,
            ]);
        }

        $winner = app(LeavePlanner::class)->matchingRule($staff->fresh(), $policy, '2026-01-01');
        $this->assertSame(30, (int) $winner?->entitled_days);

        $staff->update(['started_on' => '2024-01-01']);
        $winner = app(LeavePlanner::class)->matchingRule($staff->fresh(), $policy, '2026-01-01');
        $this->assertSame(28, (int) $winner?->entitled_days);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-01',
            'reason' => 'One day',
        ])->assertSessionHas('status');
        $this->assertSame(28, (int) LeaveBalance::query()->value('entitled_days'));
    }

    /**
     * PHPUnit uses one SQLite connection, so two PHP processes cannot lock the same
     * in-memory database. This proves the second reservation sees the first.
     */
    #[Test]
    public function two_requests_cannot_reserve_more_days_than_the_balance(): void
    {
        [$admin, $member] = $this->people();
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ]);
        $type = LeaveType::query()->firstOrFail();

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-06',
            'reason' => 'Six days',
        ])->assertSessionHas('status');
        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-08',
            'ends_on' => '2026-12-13',
            'reason' => 'Six more',
        ])->assertSessionHasErrors('leave');

        $balance = LeaveBalance::query()->firstOrFail();
        $this->assertSame(6, (int) $balance->pending_days);
        $this->assertLessThanOrEqual(
            (int) $balance->entitled_days + (int) $balance->adjusted_days,
            (int) $balance->pending_days + (int) $balance->used_days,
        );
    }

    #[Test]
    public function an_adjustment_cannot_drop_the_balance_below_reserved_days(): void
    {
        [$admin, $member, $staff] = $this->people();
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ]);
        $type = LeaveType::query()->firstOrFail();
        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-06',
            'reason' => 'Six days',
        ])->assertSessionHas('status');

        $this->actingAs($admin)->post(route('org.leave.adjustments.store'), [
            'staff_uuid' => $staff->uuid,
            'leave_type_uuid' => $type->uuid,
            'days' => -9,
            'reason' => 'Too much',
        ])->assertSessionHasErrors('leave');
        $this->assertSame(0, (int) LeaveBalance::query()->value('adjusted_days'));
    }

    #[Test]
    public function a_request_that_crosses_the_leave_year_is_refused(): void
    {
        [$admin, $member] = $this->people();
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '20',
        ]);
        $type = LeaveType::query()->firstOrFail();
        $type->policies()->firstOrFail()->update(['year_start_month' => 7, 'year_start_day' => 1]);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-06-28',
            'ends_on' => '2026-07-02',
            'reason' => 'Across the year',
        ])->assertSessionHasErrors('leave');
        $this->assertSame(0, LeaveRequest::query()->count());
    }

    #[Test]
    public function self_approval_follows_the_policy_and_closed_requests_stay_closed(): void
    {
        [$admin, $member, $staff] = $this->people();
        $manager = $this->createMember($admin->organization, role: 'organization_manager');
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($admin)->create();
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ]);
        $type = LeaveType::query()->firstOrFail();
        $policy = $type->policies()->firstOrFail();

        $this->actingAs($admin)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-01',
            'reason' => 'Mine',
        ])->assertSessionHas('status');
        $own = LeaveRequest::query()->firstOrFail();
        $this->actingAs($admin)->post(route('org.leave.decide', $own), [
            'decision' => 'approve',
        ])->assertSessionHasErrors('leave');

        $policy->update(['allow_self_approval' => true]);
        $this->actingAs($admin)->post(route('org.leave.decide', $own), [
            'decision' => 'approve',
            'decision_note' => 'Policy allows it',
        ])->assertSessionHas('status');
        $this->assertSame('approved', $own->fresh()->status->value);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-02',
            'reason' => 'Theirs',
        ])->assertSessionHas('status');
        $theirs = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $this->actingAs($manager)->post(route('org.leave.decide', $theirs), [
            'decision' => 'reject',
            'decision_note' => 'No',
        ])->assertSessionHas('status');
        $this->actingAs($manager)->post(route('org.leave.decide', $theirs), [
            'decision' => 'approve',
            'decision_note' => 'Changed my mind',
        ])->assertSessionHasErrors('leave');
        $this->assertSame('rejected', $theirs->fresh()->status->value);

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-03',
            'ends_on' => '2026-12-03',
            'reason' => 'Another',
        ])->assertSessionHas('status');
        $again = LeaveRequest::query()->where('status', 'submitted')->firstOrFail();
        $this->actingAs($manager)->post(route('org.leave.decide', $again), [
            'decision' => 'approve',
            'decision_note' => 'Yes',
        ])->assertSessionHas('status');
        $this->actingAs($manager)->post(route('org.leave.cancel', $again), [
            'decision_note' => 'Stop',
        ])->assertSessionHas('status');
        $this->actingAs($manager)->post(route('org.leave.cancel', $again), [
            'decision_note' => 'Again',
        ])->assertSessionHasErrors('leave');
        $this->assertSame('cancelled', $again->fresh()->status->value);
    }

    #[Test]
    public function another_organisation_cannot_assign_a_person_or_adjust_a_balance(): void
    {
        [$admin, , $staff] = $this->people();
        $shift = $this->shift($admin, 'Day', '08:00', '16:00');
        $stranger = $this->createOrganizationAdmin();

        $this->actingAs($stranger)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertNotFound();

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ]);
        $type = LeaveType::query()->firstOrFail();
        $this->actingAs($stranger)->post(route('org.leave.adjustments.store'), [
            'staff_uuid' => $staff->uuid,
            'leave_type_uuid' => $type->uuid,
            'days' => 5,
            'reason' => 'Not yours',
        ])->assertSessionHasErrors();
        $this->assertSame(0, LeaveBalance::query()->count());
    }

    /**
     * @return array{0: User, 1: User, 2: StaffMember}
     */
    private function people(): array
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $staff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($member)->create([
            'work_type' => WorkType::Office,
        ]);

        return [$admin, $member, $staff];
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

        return $shift;
    }
}
