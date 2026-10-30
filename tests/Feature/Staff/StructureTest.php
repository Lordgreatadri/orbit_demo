<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Location;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Departments and approved locations.
 */
class StructureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function departments_can_be_created_edited_and_deleted_when_unused(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->post(route('org.departments.store'), ['name' => ' Field Services ', 'code' => 'fs', 'description' => 'Regional inspections'])
            ->assertRedirect(route('org.departments.index'))
            ->assertSessionHas('status');

        $department = Department::withoutTenantScope()->sole();
        $this->assertSame('Field Services', $department->name);
        $this->assertSame('FS', $department->code);
        $this->assertTrue($department->is_active);
        $this->assertSame($admin->organization_id, $department->organization_id);

        // Duplicate name within the organisation is rejected; another organisation may reuse it.
        $this->actingAs($admin)->post(route('org.departments.store'), ['name' => 'Field Services'])->assertSessionHasErrors('name');
        $this->actingAs($this->createOrganizationAdmin())->post(route('org.departments.store'), ['name' => 'Field Services'])->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(route('org.departments.update', $department), ['name' => 'Field Operations', 'code' => 'FS', 'is_active' => '0'])
            ->assertRedirect(route('org.departments.index'));
        $department->refresh();
        $this->assertSame('Field Operations', $department->name);
        $this->assertFalse($department->is_active);
        $this->assertDatabaseHas('activity_log', ['event' => 'department.updated', 'subject_id' => $department->getKey()]);

        $this->actingAs($admin)->delete(route('org.departments.destroy', $department))->assertRedirect(route('org.departments.index'));
        $this->assertDatabaseMissing('departments', ['id' => $department->getKey()]);
    }

    #[Test]
    public function departments_with_staff_cannot_be_deleted(): void
    {
        $admin = $this->createOrganizationAdmin();
        $department = Department::factory()->create(['organization_id' => $admin->organization_id]);
        StaffMember::factory()->forOrganization($admin->organization)->create(['department_id' => $department->getKey()]);

        $this->actingAs($admin)
            ->delete(route('org.departments.destroy', $department))
            ->assertRedirect(route('org.departments.index'))
            ->assertSessionHasErrors('department');

        $this->assertDatabaseHas('departments', ['id' => $department->getKey()]);
    }

    #[Test]
    public function departments_are_isolated_and_members_are_kept_out(): void
    {
        $admin = $this->createOrganizationAdmin();
        $foreign = Department::factory()->create(['name' => 'Foreign Faculty']);

        $this->actingAs($admin)->get(route('org.departments.index'))->assertOk()->assertDontSee('Foreign Faculty');
        $this->actingAs($admin)->put(route('org.departments.update', $foreign), ['name' => 'Hijacked'])->assertNotFound();
        $this->assertSame('Foreign Faculty', $foreign->fresh()->name);

        $member = $this->createMember($admin->organization);
        $this->actingAs($member)->get(route('org.departments.index'))->assertForbidden();
        $this->actingAs($member)->post(route('org.departments.store'), ['name' => 'Nope'])->assertForbidden();
    }

    #[Test]
    public function locations_support_an_optional_all_or_nothing_geofence(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->post(route('org.locations.store'), ['name' => 'Head Office', 'type' => 'office', 'city' => 'Accra'])
            ->assertRedirect(route('org.locations.index'));

        $office = Location::withoutTenantScope()->sole();
        $this->assertFalse($office->hasGeofence());

        $this->actingAs($admin)
            ->post(route('org.locations.store'), ['name' => 'Tema Site', 'type' => 'field_area', 'latitude' => '5.6698', 'longitude' => '-0.0166'])
            ->assertSessionHasErrors('geofence_radius_m');

        $this->actingAs($admin)
            ->post(route('org.locations.store'), ['name' => 'Tema Site', 'type' => 'field_area', 'latitude' => '5.6698', 'longitude' => '-0.0166', 'geofence_radius_m' => '150'])
            ->assertSessionHasNoErrors();

        $site = Location::withoutTenantScope()->where('name', 'Tema Site')->sole();
        $this->assertTrue($site->hasGeofence());
        $this->assertSame(150, $site->geofence_radius_m);
        $this->assertDatabaseHas('activity_log', ['event' => 'location.created', 'subject_id' => $site->getKey()]);

        $this->actingAs($admin)
            ->post(route('org.locations.store'), ['name' => 'Bad', 'type' => 'office', 'latitude' => '95', 'longitude' => '0', 'geofence_radius_m' => '5'])
            ->assertSessionHasErrors(['latitude', 'geofence_radius_m']);
    }

    #[Test]
    public function only_administrators_manage_locations_and_managers_may_view(): void
    {
        $admin = $this->createOrganizationAdmin();
        $manager = $this->createMember($admin->organization, role: 'organization_manager');
        $location = Location::factory()->create(['organization_id' => $admin->organization_id]);
        StaffMember::factory()->forOrganization($admin->organization)->create(['primary_location_id' => $location->getKey()]);

        $this->actingAs($manager)->get(route('org.locations.index'))->assertOk()->assertSee($location->name)->assertDontSee('New location');
        $this->actingAs($manager)->post(route('org.locations.store'), ['name' => 'X', 'type' => 'office'])->assertForbidden();

        // In use: deletion refused.
        $this->actingAs($admin)->delete(route('org.locations.destroy', $location))->assertSessionHasErrors('location');
        $this->assertDatabaseHas('locations', ['id' => $location->getKey()]);

        $foreign = Location::factory()->create();
        $this->actingAs($admin)->delete(route('org.locations.destroy', $foreign))->assertNotFound();
    }
}
