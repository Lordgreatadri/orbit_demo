<?php

namespace Tests\Feature\Analytics;

use App\Analytics\AnalyticsScope;
use App\Analytics\Insights\DeterministicInsightProvider;
use App\Analytics\OperationalDigest;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\OrganizationType;
use App\Enums\ShiftStatus;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\StaffMember;
use App\Models\User;
use App\Workforce\WorkforceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function an_organisation_dashboard_counts_only_that_tenant(): void
    {
        $admin = $this->createOrganizationAdmin(
            Organization::factory()->create(['type' => OrganizationType::Business]),
        );
        StaffMember::factory()->forOrganization($admin->organization)->count(3)->create(['status' => 'active']);
        StaffMember::factory()->create(['first_name' => 'Other', 'last_name' => 'Tenant']);

        $this->actingAs($admin)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Organisation overview')
            ->assertSee('Active staff')
            ->assertSee('3 on the register')
            ->assertDontSee('Other Tenant')
            ->assertDontSee('Sample attendance metrics');
    }

    #[Test]
    public function a_member_sees_only_a_personal_workspace(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($member)->create();
        StaffMember::factory()->forOrganization($admin->organization)->create([
            'first_name' => 'Hidden',
            'last_name' => 'Colleague',
        ]);

        $this->actingAs($member)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Your workspace')
            ->assertSee('Recent attendance')
            ->assertDontSee('Hidden Colleague')
            ->assertDontSee('Active staff')
            ->assertDontSee('Organisation overview');

        $this->actingAs($member)->get(route('org.analytics.index'))->assertForbidden();
    }

    #[Test]
    public function a_department_head_cannot_see_another_department(): void
    {
        $admin = $this->createOrganizationAdmin();
        $today = now($admin->organization->timezone)->toDateString();
        $operations = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Operations']);
        $finance = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Finance']);
        $manager = $this->createMember($admin->organization, 'organization_manager');
        $head = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create([
            'department_id' => $operations->getKey(),
        ]);
        $operations->update(['head_staff_member_id' => $head->getKey()]);
        $visible = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $operations->getKey(),
            'first_name' => 'Visible',
            'last_name' => 'Officer',
        ]);
        $hidden = StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $finance->getKey(),
            'first_name' => 'Hidden',
            'last_name' => 'Colleague',
            'staff_number' => 'HID-100',
        ]);
        $this->record($visible, $today, AttendanceStatus::Present);
        $this->record($hidden, $today, AttendanceStatus::Present);

        $manager->unsetRelation('staffMember');

        $this->actingAs($manager)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Department overview')
            ->assertSee('Operations')
            ->assertDontSee('Hidden Colleague');

        $this->actingAs($manager)
            ->get(route('org.attendance.index'))
            ->assertOk()
            ->assertSee('Visible Officer')
            ->assertDontSee('Hidden Colleague');

        $this->actingAs($manager)
            ->get(route('org.staff.index'))
            ->assertOk()
            ->assertSee('Visible Officer')
            ->assertDontSee('Hidden Colleague');

        $this->actingAs($manager)
            ->get(route('org.staff.show', $hidden))
            ->assertForbidden();

        $export = $this->actingAs($manager)->get(route('org.analytics.export', [
            'from' => $today,
            'to' => $today,
            'department' => $finance->uuid,
        ]));
        $export->assertOk();
        $this->assertStringNotContainsString('HID-100', $export->streamedContent());
        $this->assertStringContainsString('Visible', $export->streamedContent());
    }

    #[Test]
    public function today_follows_the_organisation_timezone(): void
    {
        $admin = $this->createOrganizationAdmin();
        $admin->organization->update(['timezone' => 'Pacific/Auckland']);
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'UTC'));
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $this->record($person, '2026-11-11', AttendanceStatus::Present);
        $this->record($person, '2026-11-10', AttendanceStatus::Late, 'day:previous');

        $scope = AnalyticsScope::forUser($admin->fresh(), '2026-11-11', '2026-11-11');
        $today = app(WorkforceReport::class)->day($admin->organization->fresh(), '2026-11-11', $scope);

        $this->assertSame(1, $today['present']);
        $this->assertSame(0, $today['late']);

        Carbon::setTestNow();
    }

    #[Test]
    public function a_person_with_two_periods_counts_once_for_today_and_twice_on_the_trend(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $day = '2026-11-04';
        $this->record($person, $day, AttendanceStatus::Present);
        $this->record($person, $day, AttendanceStatus::Present, 'session:second');
        $scope = AnalyticsScope::forUser($admin, $day, $day);
        $report = app(WorkforceReport::class);

        $this->assertSame(1, $report->day($admin->organization, $day, $scope)['present']);
        $this->assertSame(2, $report->trend($admin->organization, Carbon::parse($day), Carbon::parse($day), $scope)[0]['present']);
    }

    #[Test]
    public function leave_utilisation_uses_the_open_balance_and_ignores_a_zero_entitlement(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $annual = LeaveType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Annual',
            'code' => 'ANNUAL',
            'paid' => true,
            'requires_balance' => true,
            'is_active' => true,
        ]);
        $unpaid = LeaveType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Unpaid',
            'code' => 'UNPAID',
            'paid' => false,
            'requires_balance' => false,
            'is_active' => true,
        ]);
        LeaveBalance::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $annual->getKey(),
            'year' => 2026,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'entitled_days' => 10,
            'adjusted_days' => 0,
            'used_days' => 4,
            'pending_days' => 1,
        ]);
        LeaveBalance::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $unpaid->getKey(),
            'year' => 2026,
            'period_start' => '2026-01-01',
            'period_end' => '2026-12-31',
            'entitled_days' => 0,
            'adjusted_days' => 0,
            'used_days' => 9,
            'pending_days' => 0,
        ]);

        $summary = app(OperationalDigest::class)->leave(AnalyticsScope::forUser($admin, '2026-11-01', '2026-11-11'));

        $this->assertSame(40, $summary['utilisation']);
        $this->assertSame(10, $summary['entitled_days']);
        $this->assertSame(4, $summary['used_days']);
    }

    #[Test]
    public function draft_shifts_do_not_count_as_schedule_coverage(): void
    {
        $admin = $this->createOrganizationAdmin();
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $draft = Shift::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Draft day',
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'effective_from' => '2026-11-01',
            'status' => ShiftStatus::Draft,
        ]);
        $published = Shift::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Published day',
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'effective_from' => '2026-11-01',
            'status' => ShiftStatus::Published,
        ]);
        ShiftAssignment::query()->create([
            'organization_id' => $admin->organization_id,
            'shift_id' => $draft->getKey(),
            'staff_member_id' => $person->getKey(),
            'work_date' => '2026-11-06',
        ]);
        ShiftAssignment::query()->create([
            'organization_id' => $admin->organization_id,
            'shift_id' => $published->getKey(),
            'staff_member_id' => $person->getKey(),
            'work_date' => '2026-11-07',
        ]);

        $coverage = app(WorkforceReport::class)->scheduleAdherence(
            $admin->organization,
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
            AnalyticsScope::forUser($admin, '2026-11-01', '2026-11-30'),
        );

        $this->assertSame(1, $coverage['scheduled']);
        $this->assertSame(0, $coverage['attended']);
    }

    #[Test]
    public function a_coverage_insight_quotes_the_stored_counts(): void
    {
        $admin = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($admin->organization)->count(5)->create(['status' => 'active']);

        $this->actingAs($admin)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Uncovered today')
            ->assertDontSee('What needs attention')
            ->assertDontSee('Attendance coverage is thin today');

        $this->actingAs($admin)
            ->get(route('org.analytics.index'))
            ->assertOk()
            ->assertSee('Action required now')
            ->assertSee('Attendance coverage is thin today')
            ->assertSee('5 of 5 active people')
            ->assertSee('Calculated')
            ->assertDontSee('AI-generated');
    }

    #[Test]
    public function cache_keys_include_the_viewer_and_the_organisation(): void
    {
        $first = $this->createOrganizationAdmin();
        $second = $this->createOrganizationAdmin();
        $left = AnalyticsScope::forUser($first, '2026-11-01', '2026-11-11')->cacheKey('attendance.today');
        $right = AnalyticsScope::forUser($second, '2026-11-01', '2026-11-11')->cacheKey('attendance.today');

        $this->assertNotSame($left, $right);
        $this->assertStringContainsString((string) $first->getKey(), $left);
        $this->assertStringContainsString((string) $first->organization_id, $left);
    }

    #[Test]
    public function a_cached_today_figure_stays_inside_its_scope_until_it_expires(): void
    {
        config(['orbit.analytics_cache_seconds' => 60]);
        Cache::flush();

        $admin = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($admin->organization)->count(4)->create(['status' => 'active']);
        $other = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($other->organization)->count(1)->create(['status' => 'active']);

        $digest = app(OperationalDigest::class);
        $scope = AnalyticsScope::forUser($admin);
        $otherScope = AnalyticsScope::forUser($other);
        $first = $digest->today($scope);

        $this->assertSame(4, $first['absent']);
        $this->assertTrue(Cache::has($scope->withDates(Carbon::parse(now($admin->organization->timezone)->toDateString()), Carbon::parse(now($admin->organization->timezone)->toDateString()))->cacheKey('attendance.today')));

        StaffMember::factory()->forOrganization($admin->organization)->create(['status' => 'active']);
        $cached = $digest->today($scope);
        $this->assertSame(4, $cached['absent']);

        $foreign = $digest->today($otherScope);
        $this->assertSame(1, $foreign['absent']);
        $this->assertNotSame(
            $scope->cacheKey('attendance.today'),
            $otherScope->cacheKey('attendance.today'),
        );

        Carbon::setTestNow(now()->addSeconds(61));
        $fresh = $digest->today($scope);
        $this->assertSame(5, $fresh['absent']);
        Carbon::setTestNow();
    }

    #[Test]
    public function an_organisation_manager_without_a_headed_department_sees_the_whole_register(): void
    {
        $admin = $this->createOrganizationAdmin();
        $operations = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Operations']);
        $finance = Department::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Finance']);
        $manager = $this->createMember($admin->organization, 'organization_manager');
        StaffMember::factory()->forOrganization($admin->organization)->linkedTo($manager)->create([
            'department_id' => $operations->getKey(),
            'first_name' => 'Wide',
            'last_name' => 'Manager',
        ]);
        StaffMember::factory()->forOrganization($admin->organization)->create([
            'department_id' => $finance->getKey(),
            'first_name' => 'Finance',
            'last_name' => 'Officer',
        ]);

        $this->actingAs($manager)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Organisation overview')
            ->assertSee('Whole organisation');

        $this->actingAs($manager)
            ->get(route('org.staff.index'))
            ->assertOk()
            ->assertSee('Finance Officer');
    }

    #[Test]
    public function the_platform_dashboard_does_not_name_tenant_staff(): void
    {
        $platform = User::factory()->platform()->create();
        $platform->assignRole('platform_super_admin');
        StaffMember::factory()->create(['first_name' => 'Confidential', 'last_name' => 'Officer']);

        $this->actingAs($platform)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Organisation registrations')
            ->assertDontSee('Confidential Officer');
    }

    #[Test]
    public function an_educational_dashboard_counts_students_and_lecturers_from_the_register(): void
    {
        $admin = $this->createOrganizationAdmin();
        $admin->organization->update(['type' => OrganizationType::EducationalInstitution]);
        StaffMember::factory()->forOrganization($admin->organization)->create([
            'job_title' => 'Student',
            'employment_type' => 'student',
        ]);
        StaffMember::factory()->forOrganization($admin->organization)->create(['job_title' => 'Lecturer']);

        $this->actingAs($admin)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Students')
            ->assertSee('Lecturers')
            ->assertSee('Not a class attendance roll');
    }

    #[Test]
    public function the_organisation_dashboard_stays_within_a_bounded_query_count(): void
    {
        $admin = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($admin->organization)->count(12)->create();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)->get(route('org.dashboard'))->assertOk();

        $this->assertLessThan(55, $queries, 'Dashboard issued '.$queries.' queries.');
    }

    #[Test]
    public function date_windows_include_the_final_day_on_sqlite(): void
    {
        $admin = $this->createOrganizationAdmin();
        $admin->organization->update(['timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
        $person = StaffMember::factory()->forOrganization($admin->organization)->create();
        $scope = AnalyticsScope::forUser($admin->fresh(), '2026-06-01', '2026-06-01');

        LeaveBalance::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => LeaveType::query()->create([
                'organization_id' => $admin->organization_id,
                'name' => 'Annual',
                'code' => 'ANNUAL',
                'paid' => true,
                'requires_balance' => true,
                'is_active' => true,
            ])->getKey(),
            'year' => 2026,
            'period_start' => '2026-06-01',
            'period_end' => '2027-05-31',
            'entitled_days' => 10,
            'adjusted_days' => 0,
            'used_days' => 4,
            'pending_days' => 0,
        ]);

        $leave = app(OperationalDigest::class)->leave($scope);
        $this->assertSame(40, $leave['utilisation']);
        $this->assertSame(10, $leave['entitled_days']);

        $typeId = LeaveType::query()->where('code', 'ANNUAL')->value('id');
        LeaveRequest::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $typeId,
            'starts_on' => '2026-06-15',
            'ends_on' => '2026-06-15',
            'day_count' => 1,
            'reason' => 'Boundary day',
            'status' => LeaveStatus::Approved,
        ]);
        LeaveRequest::query()->create([
            'organization_id' => $admin->organization_id,
            'staff_member_id' => $person->getKey(),
            'leave_type_id' => $typeId,
            'starts_on' => '2026-06-16',
            'ends_on' => '2026-06-16',
            'day_count' => 1,
            'reason' => 'Outside the window',
            'status' => LeaveStatus::Approved,
        ]);
        $this->assertSame(1, app(OperationalDigest::class)->leave($scope)['upcoming_approved']);

        Shift::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Day seven',
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'effective_from' => '2026-06-07',
            'status' => ShiftStatus::Published,
        ]);
        Shift::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Day eight',
            'starts_at' => '08:00:00',
            'ends_at' => '16:00:00',
            'effective_from' => '2026-06-08',
            'status' => ShiftStatus::Published,
        ]);
        $this->assertSame(1, app(OperationalDigest::class)->schedule($scope)['unassigned_shifts']);
    }

    #[Test]
    public function leave_concentration_includes_leave_starting_on_the_fourteenth_day(): void
    {
        $admin = $this->createOrganizationAdmin();
        $admin->organization->update(['timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-06-01 09:00:00', 'UTC'));
        $department = Department::factory()->forOrganization($admin->organization)->create(['name' => 'Operations']);
        $people = StaffMember::factory()->forOrganization($admin->organization)->count(5)->create([
            'department_id' => $department->getKey(),
            'status' => 'active',
        ]);
        $type = LeaveType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Annual',
            'code' => 'ANNUAL',
            'paid' => true,
            'requires_balance' => true,
            'is_active' => true,
        ]);

        foreach ($people->take(2) as $person) {
            LeaveRequest::query()->create([
                'organization_id' => $admin->organization_id,
                'staff_member_id' => $person->getKey(),
                'leave_type_id' => $type->getKey(),
                'starts_on' => '2026-06-14',
                'ends_on' => '2026-06-14',
                'day_count' => 1,
                'reason' => 'Boundary day',
                'status' => LeaveStatus::Approved,
            ]);
        }

        $insights = app(DeterministicInsightProvider::class)->forScope(
            AnalyticsScope::forUser($admin->fresh(), '2026-06-01', '2026-06-01'),
        );

        $this->assertTrue(collect($insights)->contains(fn ($insight) => $insight->code === 'leave_concentration'));
    }

    #[Test]
    public function the_personal_dashboard_counts_every_submitted_request_and_unlimited_leave(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $person = StaffMember::factory()->forOrganization($admin->organization)->linkedTo($member)->create();
        $annual = LeaveType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Annual',
            'code' => 'ANNUAL',
            'paid' => true,
            'requires_balance' => false,
            'default_days' => 0,
            'is_active' => true,
        ]);
        LeaveType::query()->create([
            'organization_id' => $admin->organization_id,
            'name' => 'Compassionate',
            'code' => 'COMP',
            'paid' => true,
            'requires_balance' => true,
            'default_days' => 3,
            'is_active' => true,
        ]);

        foreach (range(1, 6) as $day) {
            LeaveRequest::query()->create([
                'organization_id' => $admin->organization_id,
                'staff_member_id' => $person->getKey(),
                'leave_type_id' => $annual->getKey(),
                'starts_on' => sprintf('2026-01-%02d', $day),
                'ends_on' => sprintf('2026-01-%02d', $day),
                'day_count' => 1,
                'reason' => 'Submitted request',
                'status' => LeaveStatus::Submitted,
            ]);
        }
        foreach (range(1, 5) as $day) {
            LeaveRequest::query()->create([
                'organization_id' => $admin->organization_id,
                'staff_member_id' => $person->getKey(),
                'leave_type_id' => $annual->getKey(),
                'starts_on' => sprintf('2026-12-%02d', $day),
                'ends_on' => sprintf('2026-12-%02d', $day),
                'day_count' => 1,
                'reason' => 'Approved request',
                'status' => LeaveStatus::Approved,
            ]);
        }

        $this->actingAs($member)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Open requests')
            ->assertSee('No day limit')
            ->assertSee('>6<', false)
            ->assertDontSee('0 days available');
    }

    private function record(StaffMember $person, string $date, AttendanceStatus $status, ?string $period = null): void
    {
        AttendanceRecord::query()->create([
            'organization_id' => $person->organization_id,
            'staff_member_id' => $person->getKey(),
            'department_id' => $person->department_id,
            'work_date' => $date,
            'period_key' => $period ?? AttendanceRecord::dayPeriod($date),
            'status' => $status,
            'source' => 'manual',
        ]);
    }
}
