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
    public function tenant_context_is_derived_from_the_authenticated_user_per_request(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)->get(route('app.dashboard'))->assertOk();

        // The middleware clears the context in terminate(); a guest request leaves it empty.
        $this->assertFalse(app(TenantContext::class)->has());

        $this->get(route('home'))->assertOk();
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
    public function platform_accounts_do_not_gain_tenant_pages_through_tenant_permissions(): void
    {
        $platformUser = User::factory()->platform()->create();
        $platformUser->assignRole('platform_support');
        $organization = Organization::factory()->create();

        $this->actingAs($platformUser)->get(route('app.dashboard'))->assertOk()->assertSee('Platform account');
        $this->actingAs($platformUser)->get(route('app.members.index'))->assertForbidden();
        $this->actingAs($platformUser)->get(route('app.organizations.edit', $organization))->assertForbidden();
    }

    #[Test]
    public function a_platform_super_admin_can_review_members_across_organisations(): void
    {
        $superAdmin = User::factory()->platform()->create();
        $superAdmin->assignRole('platform_super_admin');
        $member = $this->createMember();

        $this->actingAs($superAdmin)
            ->get(route('app.members.index'))
            ->assertOk()
            ->assertSee($member->email)
            ->assertSee($member->organization->name)
            ->assertSee('Read only');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'platform.members.viewed',
            'causer_id' => $superAdmin->getKey(),
        ]);

        $this->actingAs($superAdmin)->get(route('app.organizations.edit', $member->organization))->assertForbidden();
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
