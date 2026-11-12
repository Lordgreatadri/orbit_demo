<?php

namespace Tests\Feature\Platform;

use App\Enums\AttendanceStatus;
use App\Enums\OrganizationType;
use App\Models\AttendanceRecord;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->platform()->create();
        $user->assignRole('platform_super_admin');

        return $user;
    }

    #[Test]
    public function the_statistics_page_shows_platform_aggregates_without_member_names(): void
    {
        $school = Organization::factory()->create(['type' => OrganizationType::EducationalInstitution, 'name' => 'Fictional Teaching College']);
        Organization::factory()->suspended()->create();
        StaffMember::factory()->forOrganization($school)->count(3)->create();
        StaffMember::factory()->forOrganization($school)->create(['first_name' => 'Confidential', 'last_name' => 'Learner']);
        $this->createMember($school);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.statistics'))
            ->assertOk()
            ->assertSee('Platform statistics')
            ->assertSee('Organisations by type')
            ->assertSee('Educational institution')
            ->assertSee('Largest organisations')
            ->assertSee('Fictional Teaching College')
            ->assertSee('Invitations')
            ->assertDontSee('Confidential Learner');
    }

    #[Test]
    public function the_statistics_page_is_platform_only_and_listed_in_the_console_navigation(): void
    {
        $this->actingAs($this->createOrganizationAdmin())
            ->get(route('admin.statistics'))
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.statistics'), false);
    }

    #[Test]
    public function the_statistics_page_stays_within_a_bounded_query_count(): void
    {
        Organization::factory()->count(6)->create()->each(
            fn (Organization $organization) => StaffMember::factory()->forOrganization($organization)->count(3)->create(),
        );
        $admin = $this->superAdmin();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($admin)->get(route('admin.statistics'))->assertOk();

        $this->assertLessThan(40, $queries, 'Statistics issued '.$queries.' queries.');
    }

    #[Test]
    public function the_workforce_overview_counts_distinct_people_per_organisation_without_names(): void
    {
        $organization = Organization::factory()->create(['name' => 'Fictional Water Board']);
        $person = StaffMember::factory()->forOrganization($organization)->create(['first_name' => 'Hidden', 'last_name' => 'Person']);
        StaffMember::factory()->forOrganization($organization)->create();
        $today = now($organization->timezone ?: 'UTC')->toDateString();

        foreach (['morning', 'afternoon'] as $period) {
            AttendanceRecord::query()->create([
                'organization_id' => $organization->getKey(),
                'staff_member_id' => $person->getKey(),
                'work_date' => $today,
                'period_key' => $today.':'.$period,
                'status' => AttendanceStatus::Present,
                'source' => 'manual',
            ]);
        }

        $this->actingAs($this->superAdmin())
            ->get(route('admin.workforce.overview'))
            ->assertOk()
            ->assertSee('Workforce overview')
            ->assertSee('Fictional Water Board')
            ->assertSee('1 covered of 2 active')
            ->assertSee('50%')
            ->assertDontSee('Hidden Person');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.workforce.overview', ['q' => 'water']))
            ->assertOk()
            ->assertSee('Fictional Water Board');
    }

    #[Test]
    public function coverage_counts_each_active_person_once_across_covering_statuses(): void
    {
        $organization = Organization::factory()->create(['name' => 'Fictional Harbour Office']);
        $person = StaffMember::factory()->forOrganization($organization)->create();
        StaffMember::factory()->forOrganization($organization)->create();
        $inactive = StaffMember::factory()->forOrganization($organization)->create(['status' => 'inactive']);
        $today = now($organization->timezone ?: 'UTC')->toDateString();

        AttendanceRecord::query()->create([
            'organization_id' => $organization->getKey(),
            'staff_member_id' => $person->getKey(),
            'work_date' => $today,
            'period_key' => $today.':morning',
            'status' => AttendanceStatus::Present,
            'source' => 'manual',
        ]);
        AttendanceRecord::query()->create([
            'organization_id' => $organization->getKey(),
            'staff_member_id' => $person->getKey(),
            'work_date' => $today,
            'period_key' => $today.':afternoon',
            'status' => AttendanceStatus::Late,
            'source' => 'manual',
        ]);
        AttendanceRecord::query()->create([
            'organization_id' => $organization->getKey(),
            'staff_member_id' => $inactive->getKey(),
            'work_date' => $today,
            'period_key' => $today.':morning',
            'status' => AttendanceStatus::Present,
            'source' => 'manual',
        ]);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.workforce.overview'))
            ->assertOk()
            ->assertSee('1 covered of 2 active')
            ->assertSee('1 present, 0 late, 0 on leave or other covering status, of 2 active', false)
            ->assertDontSee('2 covered of 2 active')
            ->assertDontSee('3 covered of 2 active');
    }
}
