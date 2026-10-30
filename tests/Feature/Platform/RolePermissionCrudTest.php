<?php

namespace Tests\Feature\Platform;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolePermissionCrudTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->platform()->create();
        $user->assignRole('platform_super_admin');

        return $user;
    }

    #[Test]
    public function custom_tenant_roles_can_be_created_edited_and_deleted(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->get(route('admin.roles.create'))->assertOk()->assertSee('Permissions');

        $this->actingAs($superAdmin)->post(route('admin.roles.store'), [
            'name' => 'Attendance Officer',
            'scope' => 'tenant',
            'description' => 'Runs attendance sessions',
            'permissions' => ['attendance.view', 'attendance.manage', 'platform.access'], // platform.* silently dropped
        ])->assertRedirect(route('admin.roles.index'))->assertSessionHas('status');

        $role = Role::findByName('attendance_officer');
        $this->assertSame('tenant', $role->scope->value);
        $this->assertFalse($role->is_system);
        $this->assertEqualsCanonicalizing(['attendance.manage', 'attendance.view'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('activity_log', ['event' => 'platform.role.created', 'subject_id' => $role->getKey()]);

        // The new role is immediately usable by the platform and by the organisation administrator.
        $member = $this->createMember();
        $this->actingAs($superAdmin)->put(route('admin.users.roles', $member), ['roles' => ['attendance_officer']])->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->can('attendance.manage'));

        $admin = $this->createOrganizationAdmin($member->organization);
        $colleague = $this->createMember($member->organization);
        $this->actingAs($admin)->put(route('org.users.roles', $colleague), ['roles' => ['attendance_officer']])->assertSessionHasNoErrors();
        $this->assertTrue($colleague->fresh()->hasRole('attendance_officer'));
        $this->actingAs($admin)->put(route('org.users.roles', $colleague), ['roles' => ['member']])->assertSessionHasNoErrors();
        $this->assertFalse($colleague->fresh()->hasRole('attendance_officer'));

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'name' => 'attendance_officer',
            'scope' => 'tenant',
            'description' => 'Runs attendance sessions and views schedules',
            'permissions' => ['attendance.view', 'schedules.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertEqualsCanonicalizing(['attendance.view', 'schedules.view'], $role->permissions->pluck('name')->all());
        $this->assertFalse($member->fresh()->can('attendance.manage'));
        $this->assertDatabaseHas('activity_log', ['event' => 'platform.role.updated', 'subject_id' => $role->getKey()]);

        // In use → delete refused; free → deleted.
        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
        $this->actingAs($superAdmin)->put(route('admin.users.roles', $member), ['roles' => ['member']])->assertSessionHasNoErrors();
        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['name' => 'attendance_officer']);
        $this->assertDatabaseHas('activity_log', ['event' => 'platform.role.deleted']);
    }

    #[Test]
    public function system_roles_keep_their_name_and_scope_and_cannot_be_deleted(): void
    {
        $superAdmin = $this->superAdmin();
        $role = Role::findByName('organization_manager');

        $this->actingAs($superAdmin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('System role');

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'name' => 'renamed_role',
            'scope' => 'platform',
            'description' => 'Tuned description',
            'permissions' => ['users.view', 'staff.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('organization_manager', $role->name);
        $this->assertSame('tenant', $role->scope->value);
        $this->assertSame('Tuned description', $role->description);
        $this->assertEqualsCanonicalizing(['staff.view', 'users.view'], $role->permissions->pluck('name')->all());

        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))->assertForbidden();
        $this->assertDatabaseHas('roles', ['name' => 'organization_manager']);
    }

    #[Test]
    public function role_names_are_validated_and_unique(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->post(route('admin.roles.store'), ['name' => 'member', 'scope' => 'tenant', 'permissions' => []])->assertSessionHasErrors('name');
        $this->actingAs($superAdmin)->post(route('admin.roles.store'), ['name' => '', 'scope' => 'tenant', 'permissions' => []])->assertSessionHasErrors('name');
        $this->actingAs($superAdmin)->post(route('admin.roles.store'), ['name' => 'ok_role', 'scope' => 'galaxy', 'permissions' => []])->assertSessionHasErrors('scope');
        $this->actingAs($superAdmin)->post(route('admin.roles.store'), ['name' => 'ok_role', 'scope' => 'tenant', 'permissions' => ['nope.nothing']])->assertSessionHasErrors('permissions.0');
    }

    #[Test]
    public function custom_permissions_can_be_managed_and_system_ones_are_protected(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->get(route('admin.permissions.index'))->assertOk()->assertSee('platform.access')->assertSee('Platform roles only');

        $this->actingAs($superAdmin)->post(route('admin.permissions.store'), ['name' => 'reports.export', 'description' => 'Export attendance reports'])
            ->assertRedirect(route('admin.permissions.index'));

        $permission = Permission::findByName('reports.export');
        $this->assertSame('reports', $permission->module);
        $this->assertFalse($permission->is_system);
        $this->assertDatabaseHas('activity_log', ['event' => 'platform.permission.created', 'subject_id' => $permission->getKey()]);

        $this->actingAs($superAdmin)->post(route('admin.permissions.store'), ['name' => 'NoDot', 'description' => 'Invalid name'])->assertSessionHasErrors('name');
        $this->actingAs($superAdmin)->post(route('admin.permissions.store'), ['name' => 'reports.export', 'description' => 'Duplicate'])->assertSessionHasErrors('name');

        $this->actingAs($superAdmin)->put(route('admin.permissions.update', $permission), ['name' => 'reports.download', 'description' => 'Download reports'])->assertRedirect();
        $this->assertSame('reports.download', $permission->fresh()->name);

        // System permission: description editable, name fixed, delete refused.
        $system = Permission::findByName('platform.access');
        $this->actingAs($superAdmin)->put(route('admin.permissions.update', $system), ['name' => 'platform.renamed', 'description' => 'Console entry'])->assertRedirect();
        $system->refresh();
        $this->assertSame('platform.access', $system->name);
        $this->assertSame('Console entry', $system->description);
        $this->actingAs($superAdmin)->delete(route('admin.permissions.destroy', $system))->assertForbidden();

        // Custom permission attached to a role cannot be deleted until detached.
        Role::findByName('organization_manager')->givePermissionTo('reports.download');
        $this->actingAs($superAdmin)->delete(route('admin.permissions.destroy', $permission))->assertSessionHasErrors('permission');
        Role::findByName('organization_manager')->revokePermissionTo('reports.download');
        $this->actingAs($superAdmin)->delete(route('admin.permissions.destroy', $permission))->assertRedirect(route('admin.permissions.index'));
        $this->assertDatabaseMissing('permissions', ['name' => 'reports.download']);
    }

    #[Test]
    public function tenant_roles_cannot_hold_platform_permissions_even_when_edited(): void
    {
        $superAdmin = $this->superAdmin();
        $role = Role::findByName('organization_admin');

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'name' => $role->name,
            'scope' => 'tenant',
            'description' => $role->description,
            'permissions' => ['platform.access', 'platform.users.manage', 'users.manage'],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(['users.manage'], $role->fresh()->permissions->pluck('name')->all());
        $this->assertFalse($this->createOrganizationAdmin()->can('platform.access'));
    }

    #[Test]
    public function a_custom_role_cannot_change_scope_after_it_is_created(): void
    {
        $superAdmin = $this->superAdmin();

        $this->actingAs($superAdmin)->post(route('admin.roles.store'), [
            'name' => 'site_supervisor',
            'scope' => 'tenant',
            'description' => 'Supervises a site',
            'permissions' => ['staff.view'],
        ])->assertRedirect();

        $role = Role::findByName('site_supervisor');
        $member = $this->createMember();
        $this->actingAs($superAdmin)->put(route('admin.users.roles', $member), ['roles' => ['site_supervisor']])->assertSessionHasNoErrors();

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'name' => 'site_supervisor',
            'scope' => 'platform',
            'description' => 'Should stay tenant-scoped',
            'permissions' => ['platform.access', 'staff.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();
        $this->assertSame('tenant', $role->scope->value);
        $this->assertFalse($role->permissions->contains('name', 'platform.access'));
        $this->assertTrue($member->fresh()->hasRole('site_supervisor'));
        $this->assertFalse($member->fresh()->can('platform.access'));
    }
}
