<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Tenancy\ProvisionOrganizationWorkspace;
use App\Models\Organization;
use App\Models\OrganizationBranding;
use App\Tenancy\Hostname;
use Database\Seeders\OrganizationWorkspaceBackfillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceProvisioningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_long_organisation_slug_falls_through_to_a_valid_hostname(): void
    {
        $organization = Organization::factory()->create([
            'slug' => str_repeat('longname', 20),
            'name' => 'Acme Review Desk',
        ]);

        $host = $organization->primaryHost();
        $label = explode('.', $host)[0];

        $this->assertLessThanOrEqual(Hostname::LABEL_MAX_LENGTH, strlen($label));
        $this->assertSame($host, Hostname::normalize($host));
        $this->assertSame('acme-review-desk.orbit.test', $host);
    }

    #[Test]
    public function the_backfill_seeder_provisions_legacy_organisations_idempotently(): void
    {
        $organization = Organization::factory()->withoutWorkspace()->create([
            'name' => 'Legacy Institute',
            'slug' => 'legacy-institute',
        ]);

        $this->assertNull($organization->branding);
        $this->assertSame(0, $organization->domains()->count());

        $this->seed(OrganizationWorkspaceBackfillSeeder::class);
        $this->seed(OrganizationWorkspaceBackfillSeeder::class);

        $organization->refresh();

        $this->assertSame(1, $organization->domains()->count());
        $this->assertNotNull($organization->branding);
        $this->assertTrue($organization->domains()->primary()->exists());
        $this->assertSame($organization->primaryHost(), Hostname::normalize($organization->primaryHost()));
    }

    #[Test]
    public function the_branding_factory_reuses_the_provisioned_row(): void
    {
        $created = OrganizationBranding::factory()->create(['display_name' => 'First pass']);
        $this->assertDatabaseCount('organization_brandings', 1);

        $updated = OrganizationBranding::factory()->for($created->organization)->create([
            'display_name' => 'Second pass',
        ]);

        $this->assertDatabaseCount('organization_brandings', 1);
        $this->assertSame($created->getKey(), $updated->getKey());
        $this->assertSame('Second pass', $updated->fresh()->display_name);
    }

    #[Test]
    public function provisioning_is_safe_to_repeat(): void
    {
        $organization = Organization::factory()->create();
        $first = $organization->primaryHost();

        app(ProvisionOrganizationWorkspace::class)->handle($organization);
        app(ProvisionOrganizationWorkspace::class)->handle($organization);

        $this->assertSame(1, $organization->domains()->count());
        $this->assertSame(1, $organization->branding()->count());
        $this->assertSame($first, $organization->fresh()->primaryHost());
    }
}
