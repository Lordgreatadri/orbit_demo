<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Location;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffRegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'staff_number' => 'epa-0101',
            'first_name' => 'Kojo',
            'last_name' => 'Appiah',
            'email' => 'Kojo.Appiah@Example.test',
            'phone' => '+233 24 400 0101',
            'job_title' => 'Field Officer',
            'work_type' => 'field',
            'status' => 'active',
            'started_on' => '2026-01-05',
        ], $overrides);
    }

    #[Test]
    public function administrators_and_managers_can_create_staff_records_in_their_organisation(): void
    {
        $admin = $this->createOrganizationAdmin();
        $department = Department::factory()->create(['organization_id' => $admin->organization_id]);
        $location = Location::factory()->create(['organization_id' => $admin->organization_id]);

        $response = $this->actingAs($admin)->post(route('org.staff.store'), $this->payload([
            'department' => $department->uuid,
            'primary_location' => $location->uuid,
        ]));

        $staff = StaffMember::withoutTenantScope()->sole();
        $response->assertRedirect(route('org.staff.show', $staff))->assertSessionHas('status');

        $this->assertSame($admin->organization_id, $staff->organization_id);
        $this->assertSame('EPA-0101', $staff->staff_number);
        $this->assertSame('kojo.appiah@example.test', $staff->email);
        $this->assertSame('+233244000101', $staff->phone);
        $this->assertSame($department->getKey(), $staff->department_id);
        $this->assertSame($location->getKey(), $staff->primary_location_id);
        $this->assertNull($staff->user_id);
        $this->assertDatabaseHas('activity_log', ['event' => 'staff.created', 'organization_id' => $admin->organization_id]);

        $manager = $this->createMember($admin->organization, role: 'organization_manager');
        $this->actingAs($manager)->post(route('org.staff.store'), $this->payload(['staff_number' => 'EPA-0102']))->assertRedirect();
        $this->assertSame(2, StaffMember::withoutTenantScope()->count());
    }

    #[Test]
    public function members_cannot_see_or_change_the_register(): void
    {
        $member = $this->createMember();
        StaffMember::factory()->forOrganization($member->organization)->create();

        $this->actingAs($member)->get(route('org.staff.index'))->assertForbidden();
        $this->actingAs($member)->post(route('org.staff.store'), $this->payload())->assertForbidden();
        $this->assertSame(1, StaffMember::withoutTenantScope()->count());
    }

    #[Test]
    public function staff_numbers_are_unique_per_organisation_and_departments_must_belong_to_the_tenant(): void
    {
        $admin = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($admin->organization)->create(['staff_number' => 'EPA-0101']);
        $foreignDepartment = Department::factory()->create();

        $this->actingAs($admin)
            ->post(route('org.staff.store'), $this->payload())
            ->assertSessionHasErrors('staff_number');

        $this->actingAs($admin)
            ->post(route('org.staff.store'), $this->payload(['staff_number' => 'EPA-0999', 'department' => $foreignDepartment->uuid]))
            ->assertSessionHasErrors('department');

        // Another organisation can reuse the same number.
        $otherAdmin = $this->createOrganizationAdmin();
        $this->actingAs($otherAdmin)->post(route('org.staff.store'), $this->payload())->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_user_account_can_be_linked_to_exactly_one_staff_record_in_the_same_organisation(): void
    {
        $admin = $this->createOrganizationAdmin();
        $colleague = $this->createMember($admin->organization);
        $stranger = $this->createMember();

        $this->actingAs($admin)
            ->post(route('org.staff.store'), $this->payload(['user' => $stranger->uuid]))
            ->assertSessionHasErrors('user');

        $this->actingAs($admin)
            ->post(route('org.staff.store'), $this->payload(['user' => $colleague->uuid]))
            ->assertSessionHasNoErrors();

        $this->assertSame($colleague->getKey(), StaffMember::withoutTenantScope()->sole()->user_id);
        $this->assertSame('EPA-0101', $colleague->fresh()->staffMember->staff_number);

        $this->actingAs($admin)
            ->post(route('org.staff.store'), $this->payload(['staff_number' => 'EPA-0102', 'user' => $colleague->uuid]))
            ->assertSessionHasErrors('user');
    }

    #[Test]
    public function staff_records_are_isolated_per_organisation(): void
    {
        $admin = $this->createOrganizationAdmin();
        $own = StaffMember::factory()->forOrganization($admin->organization)->create(['first_name' => 'Abena', 'last_name' => 'Darko']);
        $foreign = StaffMember::factory()->create(['first_name' => 'Yaw', 'last_name' => 'Foreign']);

        $this->actingAs($admin)
            ->get(route('org.staff.index'))
            ->assertOk()
            ->assertSee('Abena Darko')
            ->assertDontSee('Yaw Foreign');

        $this->actingAs($admin)->get(route('org.staff.show', $own))->assertOk()->assertSee($own->staff_number);
        $this->actingAs($admin)->get(route('org.staff.show', $foreign))->assertNotFound();
        $this->actingAs($admin)->put(route('org.staff.update', $foreign), $this->payload())->assertNotFound();
        $this->actingAs($admin)->delete(route('org.staff.destroy', $foreign))->assertNotFound();

        $this->assertNull(StaffMember::withoutTenantScope()->find($foreign->getKey())->deleted_at);
    }

    #[Test]
    public function staff_records_can_be_updated_filtered_and_archived(): void
    {
        $admin = $this->createOrganizationAdmin();
        $staff = StaffMember::factory()->forOrganization($admin->organization)->create(['work_type' => 'office', 'status' => 'active']);

        $this->actingAs($admin)
            ->put(route('org.staff.update', $staff), $this->payload(['staff_number' => $staff->staff_number, 'work_type' => 'remote', 'status' => 'on_leave']))
            ->assertRedirect(route('org.staff.show', $staff));

        $staff->refresh();
        $this->assertSame('remote', $staff->work_type->value);
        $this->assertSame('on_leave', $staff->status->value);
        $this->assertDatabaseHas('activity_log', ['event' => 'staff.updated', 'subject_id' => $staff->getKey()]);

        $this->actingAs($admin)->get(route('org.staff.index', ['status' => 'active']))->assertOk()->assertDontSee($staff->staff_number);
        $this->actingAs($admin)->get(route('org.staff.index', ['work_type' => 'remote']))->assertOk()->assertSee($staff->staff_number);
        $this->actingAs($admin)->get(route('org.staff.index', ['q' => $staff->last_name]))->assertOk()->assertSee($staff->staff_number);

        $this->actingAs($admin)->delete(route('org.staff.destroy', $staff))->assertRedirect(route('org.staff.index'));
        $this->assertSoftDeleted('staff_members', ['id' => $staff->getKey()]);
        $this->assertDatabaseHas('activity_log', ['event' => 'staff.archived', 'subject_id' => $staff->getKey()]);
    }

    #[Test]
    public function the_dashboard_summarises_real_counts_only(): void
    {
        $admin = $this->createOrganizationAdmin();
        StaffMember::factory()->forOrganization($admin->organization)->count(3)->create(['status' => 'active', 'work_type' => 'field']);
        StaffMember::factory()->forOrganization($admin->organization)->create(['status' => 'on_leave']);
        StaffMember::factory()->create(); // another organisation

        $this->actingAs($admin)
            ->get(route('org.dashboard'))
            ->assertOk()
            ->assertSee('Active staff')
            ->assertSee('4 on the register')
            ->assertSee('Setup checklist')
            ->assertDontSee('Sample attendance metrics');
    }

    #[Test]
    public function tenant_scoping_applies_to_the_new_structure_tables(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        Department::factory()->count(2)->create(['organization_id' => $a->getKey()]);
        Department::factory()->create(['organization_id' => $b->getKey()]);
        Location::factory()->create(['organization_id' => $a->getKey()]);
        StaffMember::factory()->forOrganization($b)->count(2)->create();

        $context = app(TenantContext::class);
        $context->set($a);
        $this->assertSame(2, Department::query()->count());
        $this->assertSame(1, Location::query()->count());
        $this->assertSame(0, StaffMember::query()->count());

        $context->set($b);
        $this->assertSame(1, Department::query()->count());
        $this->assertSame(2, StaffMember::query()->count());
        $context->clear();

        foreach ([Department::query()->first(), Location::query()->first(), StaffMember::withoutTenantScope()->first()] as $model) {
            $this->assertIsInt($model->getKey());
            $this->assertSame('uuid', $model->getRouteKeyName());
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $model->uuid);
        }

        $this->assertInstanceOf(User::class, User::factory()->create());
    }
}
