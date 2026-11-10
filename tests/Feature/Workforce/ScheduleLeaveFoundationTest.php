<?php

namespace Tests\Feature\Workforce;

use App\Enums\WorkType;
use App\Models\AttendanceRecord;
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
use App\Models\WorkforcePolicy;
use App\Workforce\LeavePlanner;
use App\Workforce\VerificationCentre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleLeaveFoundationTest extends TestCase
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
    public function two_work_periods_on_one_date_are_kept_when_they_do_not_overlap(): void
    {
        [$admin, $member, $staff] = $this->people();

        $morning = $this->shift($admin, 'Morning', '08:00', '12:00');
        $afternoon = $this->shift($admin, 'Afternoon', '13:00', '17:00');

        $this->actingAs($admin)->post(route('org.schedules.assign', $morning), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $afternoon), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');

        $this->assertSame(2, ShiftAssignment::query()->where('staff_member_id', $staff->getKey())->count());

        $this->actingAs($admin)->post(route('org.schedules.assign', $morning), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHasErrors('schedule');
    }

    #[Test]
    public function an_overlapping_second_shift_is_refused(): void
    {
        [$admin, , $staff] = $this->people();
        $first = $this->shift($admin, 'Day', '08:00', '16:00');
        $second = $this->shift($admin, 'Overlap', '15:00', '20:00');

        $this->actingAs($admin)->post(route('org.schedules.assign', $first), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('org.schedules.assign', $second), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-06',
        ])->assertSessionHasErrors('schedule');
        $this->assertSame(1, ShiftAssignment::query()->count());
    }

    #[Test]
    public function a_remote_period_lets_an_office_person_check_in_without_a_geofence(): void
    {
        [$admin, $member, $staff] = $this->people();
        $this->actingAs($admin);
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $remote = WorkArrangement::query()->where('code', 'remote')->firstOrFail();
        $shift = $this->shift($admin, 'Home day', '08:00', '16:00', $remote->uuid);

        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-04',
            'arrangement_uuid' => $remote->uuid,
        ])->assertSessionHas('status');

        $this->actingAs($member)->post(route('org.attendance.check-in'), [
            'methods' => ['gps'],
            'latitude' => 5.6,
            'longitude' => -0.18,
            'accuracy_m' => 20,
            'captured_at' => now()->toIso8601String(),
        ])->assertSessionHas('status');

        $record = AttendanceRecord::query()->where('staff_member_id', $staff->getKey())->firstOrFail();
        $this->assertSame('present', $record->status->value);
        $this->assertSame(WorkType::Office, $staff->work_type);
    }

    #[Test]
    public function an_office_period_does_not_treat_a_hybrid_person_as_remote(): void
    {
        [$admin, $member, $staff] = $this->people();
        $staff->update(['work_type' => WorkType::Hybrid]);
        app(LeavePlanner::class)->ensureArrangements($admin->organization);
        $office = WorkArrangement::query()->where('code', 'office')->firstOrFail();
        $shift = $this->shift($admin, 'Head office', '08:00', '16:00', $office->uuid);
        $this->actingAs($admin)->post(route('org.schedules.assign', $shift), [
            'staff_uuid' => $staff->uuid,
            'work_date' => '2026-11-04',
            'arrangement_uuid' => $office->uuid,
        ])->assertSessionHas('status');

        $policy = WorkforcePolicy::resolve($admin->organization);
        $decision = app(VerificationCentre::class)->assess($member, $staff->fresh(), $policy, [
            'methods' => ['gps'],
            'latitude' => 5.6,
            'longitude' => -0.18,
            'accuracy_m' => 20,
            'captured_at' => now()->toIso8601String(),
        ], 'office');

        $this->assertFalse($decision->acceptedStandalone());
    }

    #[Test]
    public function entitlement_rules_are_tenant_data_and_do_not_rewrite_an_open_balance(): void
    {
        [$admin, $member, $staff] = $this->people();
        $category = StaffCategory::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Teaching',
            'code' => 'TEACH',
        ]);
        $staff->update(['staff_category_id' => $category->getKey(), 'grade' => 'A']);

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ])->assertSessionHas('status');
        $type = LeaveType::query()->firstOrFail();

        $this->actingAs($admin)->post(route('org.leave.rules.store'), [
            'leave_type_uuid' => $type->uuid,
            'priority' => 10,
            'entitled_days' => 26,
            'effective_from' => '2026-01-01',
            'staff_category_uuid' => $category->uuid,
        ])->assertSessionHas('status');

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-11-10',
            'ends_on' => '2026-11-10',
            'reason' => 'One day',
        ])->assertSessionHas('status');

        $this->assertSame(26, (int) LeaveBalance::query()->value('entitled_days'));

        LeaveEntitlementRule::query()->update(['entitled_days' => 30]);
        $this->assertSame(26, (int) LeaveBalance::query()->value('entitled_days'));

        $stranger = $this->createOrganizationAdmin();
        $this->actingAs($stranger)->post(route('org.leave.rules.store'), [
            'leave_type_uuid' => $type->uuid,
            'priority' => 1,
            'entitled_days' => 99,
            'effective_from' => '2026-01-01',
        ])->assertSessionHasErrors('leave_type_uuid');
    }

    #[Test]
    public function leave_cannot_be_self_approved_withdrawn_by_someone_else_or_double_approved(): void
    {
        [$admin, $member, $staff] = $this->people();
        $manager = $this->createMember($admin->organization, role: 'organization_manager');
        $adminStaff = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($admin)->create();

        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '5',
        ]);
        $type = LeaveType::query()->firstOrFail();

        $this->actingAs($admin)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-01',
            'ends_on' => '2026-12-01',
            'reason' => 'Own day',
        ])->assertSessionHas('status');
        $own = LeaveRequest::query()->where('staff_member_id', $adminStaff->getKey())->firstOrFail();
        $this->actingAs($admin)->post(route('org.leave.decide', $own), [
            'decision' => 'approve',
            'decision_note' => 'Myself',
        ])->assertSessionHasErrors('leave');

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-02',
            'ends_on' => '2026-12-02',
            'reason' => 'Their day',
        ])->assertSessionHas('status');
        $theirs = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->firstOrFail();

        $this->actingAs($manager)->post(route('org.leave.withdraw', $theirs))->assertSessionHasErrors('leave');
        $this->actingAs($member)->post(route('org.leave.withdraw', $theirs))->assertSessionHas('status');
        $this->assertSame('withdrawn', $theirs->fresh()->status->value);
        $this->assertSame(0, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('pending_days'));

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-12-03',
            'ends_on' => '2026-12-03',
            'reason' => 'Again',
        ])->assertSessionHas('status');
        $again = LeaveRequest::query()->where('staff_member_id', $staff->getKey())->where('status', 'submitted')->firstOrFail();
        $this->actingAs($manager)->post(route('org.leave.decide', $again), [
            'decision' => 'approve',
            'decision_note' => 'Yes',
        ])->assertSessionHas('status');
        $this->actingAs($manager)->post(route('org.leave.cancel', $again), [
            'decision_note' => 'Plans changed',
        ])->assertSessionHas('status');
        $this->assertSame('cancelled', $again->fresh()->status->value);
        $this->assertSame(0, (int) LeaveBalance::query()->where('staff_member_id', $staff->getKey())->value('used_days'));
        $this->assertSame(0, AttendanceRecord::query()->where('source', 'leave')->count());
    }

    #[Test]
    public function a_working_day_policy_skips_the_tenant_weekend_and_a_holiday(): void
    {
        [$admin, $member, $staff] = $this->people();
        $this->actingAs($admin)->post(route('org.leave.types.store'), [
            'name' => 'Annual',
            'code' => 'annual',
            'requires_balance' => '1',
            'default_days' => '10',
        ]);
        $type = LeaveType::query()->firstOrFail();
        $policy = $type->policies()->firstOrFail();
        $policy->update(['count_mode' => 'working', 'weekend_days' => [6, 7], 'exclude_non_working' => true]);

        $this->actingAs($admin)->post(route('org.schedules.calendar.store'), [
            'observed_on' => '2026-11-11',
            'name' => 'Foundation day',
            'kind' => 'holiday',
        ])->assertSessionHas('status');

        $this->actingAs($member)->post(route('org.leave.store'), [
            'leave_type_uuid' => $type->uuid,
            'starts_on' => '2026-11-09',
            'ends_on' => '2026-11-15',
            'reason' => 'A week',
        ])->assertSessionHas('status');

        $request = LeaveRequest::query()->firstOrFail();
        $this->assertSame(4, $request->dayCount());
        $this->assertNotContains('2026-11-11', $request->coveredDates());
        $this->assertNotContains('2026-11-14', $request->coveredDates());
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
