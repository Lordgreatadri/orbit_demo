<?php

namespace Tests\Feature\Tenancy;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tenant_owned_queries_are_scoped_to_the_current_tenant(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        Invitation::factory()->forOrganization($a)->count(2)->create();
        Invitation::factory()->forOrganization($b)->count(3)->create();

        $context = app(TenantContext::class);

        $context->set($a);
        $this->assertSame(2, Invitation::query()->count());

        $context->set($b);
        $this->assertSame(3, Invitation::query()->count());

        $context->clear();
        $this->assertSame(5, Invitation::query()->count());
        $this->assertSame(5, Invitation::withoutTenantScope()->count());
    }

    #[Test]
    public function tenant_owned_models_are_stamped_with_the_current_tenant_on_create(): void
    {
        $a = Organization::factory()->create();
        app(TenantContext::class)->set($a);

        [, $hash] = Invitation::generateToken();
        $invitation = Invitation::query()->create([
            'email' => 'auto@example.test',
            'role' => 'member',
            'token_hash' => $hash,
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame($a->getKey(), $invitation->organization_id);
        app(TenantContext::class)->clear();
    }

    #[Test]
    public function tenant_context_is_derived_from_the_hostname_and_cleared_after_the_request(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)->get(route('app.dashboard'))->assertRedirect(route('org.dashboard'));
        $this->actingAs($admin)->get(route('org.dashboard'))->assertOk();

        $this->assertFalse(app(TenantContext::class)->has());

        $this->fromCentral()->get(route('home'))->assertOk();
        $this->assertFalse(app(TenantContext::class)->has());
    }

    #[Test]
    public function platform_roles_cannot_be_assigned_to_organisation_accounts(): void
    {
        $tenantUser = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $tenantUser->assignRole('platform_super_admin');
    }

    #[Test]
    public function tenant_roles_cannot_be_assigned_to_platform_accounts(): void
    {
        $platformUser = User::factory()->platform()->create();

        $this->expectException(InvalidArgumentException::class);
        $platformUser->assignRole('organization_admin');
    }

    #[Test]
    public function tenant_permissions_cannot_be_assigned_to_platform_accounts(): void
    {
        $platformUser = User::factory()->platform()->create();

        $this->expectException(InvalidArgumentException::class);
        $platformUser->givePermissionTo('staff.view');
    }

    #[Test]
    public function platform_accounts_do_not_gain_tenant_pages_through_tenant_permissions(): void
    {
        $platformUser = User::factory()->platform()->create();
        $platformUser->assignRole('platform_support');
        $organization = Organization::factory()->create();

        // Platform accounts land in the platform console and are kept out of every tenant route.
        $this->actingAs($platformUser)->get(route('app.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($platformUser)->get(route('app.profile'))->assertOk()->assertSee('Platform account');
        $this->actingAs($platformUser)->get(route('org.dashboard'))->assertForbidden();
        $this->actingAs($platformUser)->get(route('org.users.index'))->assertForbidden();
        $this->actingAs($platformUser)->get(route('org.staff.index'))->assertForbidden();
        $this->actingAs($platformUser)->get(route('org.organizations.edit', $organization))->assertForbidden();
    }

    #[Test]
    public function tenant_accounts_cannot_reach_the_platform_console(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.organizations.index'))->assertForbidden();
    }

    #[Test]
    public function a_platform_super_admin_can_review_users_across_organisations(): void
    {
        $superAdmin = User::factory()->platform()->create();
        $superAdmin->assignRole('platform_super_admin');
        $member = $this->createMember();

        $this->actingAs($superAdmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($member->email)
            ->assertSee($member->organization->name);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'platform.users.viewed',
            'causer_id' => $superAdmin->getKey(),
        ]);

        $this->actingAs($superAdmin)->get(route('admin.users.show', $member))->assertOk()->assertSee($member->email);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'platform.user.viewed',
            'subject_id' => $member->getKey(),
            'organization_id' => $member->organization_id,
        ]);

        // Tenant settings remain with the organisation's own administrators.
        $this->actingAs($superAdmin)->get(route('org.organizations.edit', $member->organization))->assertForbidden();
    }

    #[Test]
    public function tenant_user_routes_resolve_only_accounts_in_the_current_organisation(): void
    {
        $admin = $this->createOrganizationAdmin();
        $stranger = $this->createMember();

        $this->actingAs($admin)->get(route('org.users.show', $stranger))->assertNotFound();
        $this->actingAs($admin)->get('/org/users/not-a-uuid')->assertNotFound();
        $this->actingAs($admin)->put(route('org.users.roles', $stranger), ['roles' => ['organization_admin']])->assertNotFound();

        $this->assertFalse($stranger->fresh()->hasRole('organization_admin'));
    }

    #[Test]
    public function every_core_table_uses_numeric_ids_with_public_uuids(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->forOrganization($organization)->create();
        $invitation = Invitation::factory()->forOrganization($organization)->create();

        foreach ([$organization, $user, $invitation] as $model) {
            $this->assertIsInt($model->getKey());
            $this->assertTrue($model->getIncrementing());
            $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $model->uuid);
            $this->assertSame('uuid', $model->getRouteKeyName());
        }
    }
}
