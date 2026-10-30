<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Renamed Fictional Agency',
            'type' => 'public_institution',
            'timezone' => 'Africa/Accra',
            'contact_email' => 'Contact@Renamed.test',
            'contact_phone' => '+233 30 000 0000',
            'address_line' => '1 Demo Road',
            'city' => 'Accra',
        ], $overrides);
    }

    #[Test]
    public function administrator_can_view_and_update_their_own_organisation(): void
    {
        $admin = $this->createOrganizationAdmin();
        $organization = $admin->organization;

        $this->actingAs($admin)
            ->get(route('org.organizations.edit', $organization))
            ->assertOk()
            ->assertSee($organization->name)
            ->assertSee($organization->uuid);

        $this->actingAs($admin)
            ->put(route('org.organizations.update', $organization), $this->payload())
            ->assertRedirect(route('org.organizations.edit', $organization))
            ->assertSessionHas('status');

        $organization->refresh();
        $this->assertSame('Renamed Fictional Agency', $organization->name);
        $this->assertSame('public_institution', $organization->type->value);
        $this->assertSame('contact@renamed.test', $organization->contact_email);
        $this->assertSame('+233300000000', $organization->contact_phone);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'organization.updated',
            'organization_id' => $organization->getKey(),
            'causer_id' => $admin->getKey(),
        ]);
    }

    #[Test]
    public function routes_use_uuid_not_numeric_id(): void
    {
        $admin = $this->createOrganizationAdmin();
        $organization = $admin->organization;

        $this->assertStringContainsString($organization->uuid, route('org.organizations.edit', $organization));
        $this->assertStringNotContainsString('/'.$organization->getKey().'/', route('org.organizations.edit', $organization));

        $this->actingAs($admin)->get('/org/organisation/'.$organization->getKey().'/settings')->assertNotFound();
        $this->actingAs($admin)->get('/org/organisation/not-a-uuid/settings')->assertNotFound();
    }

    #[Test]
    public function administrator_cannot_view_or_update_another_organisation_by_guessing_its_uuid(): void
    {
        $admin = $this->createOrganizationAdmin();
        $other = Organization::factory()->create(['name' => 'Other Tenant Ltd']);

        $this->actingAs($admin)->get(route('org.organizations.edit', $other))->assertForbidden();

        $this->actingAs($admin)
            ->put(route('org.organizations.update', $other), $this->payload())
            ->assertForbidden();

        $this->assertSame('Other Tenant Ltd', $other->fresh()->name);
    }

    #[Test]
    public function members_without_permission_cannot_open_settings(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)->get(route('org.organizations.edit', $member->organization))->assertForbidden();
        $this->actingAs($member)->put(route('org.organizations.update', $member->organization), $this->payload())->assertForbidden();
    }

    #[Test]
    public function managers_can_view_but_not_update(): void
    {
        $manager = $this->createMember(role: 'organization_manager');

        $this->actingAs($manager)->get(route('org.organizations.edit', $manager->organization))
            ->assertOk()
            ->assertSee('only an organisation administrator can change them');

        $this->actingAs($manager)->put(route('org.organizations.update', $manager->organization), $this->payload())->assertForbidden();
    }

    #[Test]
    public function invalid_settings_are_rejected(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->from(route('org.organizations.edit', $admin->organization))
            ->put(route('org.organizations.update', $admin->organization), $this->payload([
                'name' => 'A',
                'type' => 'nope',
                'timezone' => 'Nowhere/Land',
                'contact_email' => 'bad',
                'contact_phone' => 'phone',
            ]))
            ->assertRedirect(route('org.organizations.edit', $admin->organization))
            ->assertSessionHasErrors(['name', 'type', 'timezone', 'contact_email', 'contact_phone']);
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $organization = Organization::factory()->create();

        $this->get(route('org.organizations.edit', $organization))->assertRedirect(route('login'));
    }
}
