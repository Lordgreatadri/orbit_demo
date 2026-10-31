<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DomainType;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Jobs\Middleware\RestoreTenantContext;
use App\Jobs\ProcessTenantMail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Tenancy\BrandingService;
use App\Tenancy\Hostname;
use App\Tenancy\Origin;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HostnameRoutingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_central_platform_host_serves_public_pages_and_not_a_tenant_workspace(): void
    {
        $this->fromCentral()->get('/')->assertOk()->assertSee('ORBIT');
        $this->fromCentral()->get(route('about'))->assertOk();
        $this->fromCentral()->get('/org')->assertRedirect(route('login'));
    }

    #[Test]
    public function a_registered_tenant_subdomain_resolves_to_that_organisation(): void
    {
        $organization = Organization::factory()->create(['name' => 'EPA Demo']);
        $host = $organization->primaryHost();

        $this->fromTenant($organization)->get('/login')
            ->assertOk()
            ->assertSee($organization->name);

        $this->assertSame($host, Hostname::normalize($host));
        $this->assertNotSame(Hostname::centralHosts()[0], $host);
    }

    #[Test]
    public function an_alias_hostname_resolves_to_the_same_organisation_as_the_primary(): void
    {
        $organization = Organization::factory()->create(['name' => 'Mobile Content Ltd']);
        $alias = Hostname::platformHostFor('mcc');
        OrganizationDomain::factory()->for($organization)->alias('mcc')->create();

        $admin = $this->createOrganizationAdmin($organization);

        $this->be($admin);
        $this->withServerVariables(['HTTP_HOST' => $alias, 'SERVER_NAME' => $alias]);
        Origin::force('http://'.$alias);

        $this->get('http://'.$alias.'/org')->assertOk();
        $this->assertSame($organization->getKey(), $admin->organization_id);
        $this->assertTrue($organization->domains()->where('domain', $alias)->where('type', DomainType::Alias)->exists());
        $this->assertNotSame($alias, $organization->fresh()->primaryHost());
    }

    #[Test]
    public function login_forms_on_an_alias_stay_on_that_hostname(): void
    {
        $organization = Organization::factory()->create(['name' => 'Mobile Content Ltd']);
        $alias = Hostname::platformHostFor('mcc');
        OrganizationDomain::factory()->for($organization)->alias('mcc')->create();

        $this->fromHost($alias)
            ->get('/login')
            ->assertOk()
            ->assertSee('action="http://'.$alias.'/login"', false)
            ->assertDontSee('action="http://'.$organization->primaryHost().'/login"', false)
            ->assertSee('http://orbit.test/terms', false);
    }

    #[Test]
    public function an_unknown_hostname_is_rejected(): void
    {
        $this->get('http://unknown.orbit.test/')->assertNotFound();
    }

    #[Test]
    public function a_closed_organisation_hostname_is_rejected(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Closed]);

        $this->fromTenant($organization)->get('/login')->assertForbidden();
    }

    #[Test]
    public function an_unverified_custom_domain_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $custom = OrganizationDomain::factory()->for($organization)->custom('attendance.example.test', verified: false)->create();

        $this->get('http://'.$custom->domain.'/login')->assertNotFound();
    }

    #[Test]
    public function reserved_subdomains_cannot_be_registered(): void
    {
        $admin = $this->createOrganizationAdmin();

        $this->actingAs($admin)
            ->post(route('org.organizations.domains.store', $admin->organization), [
                'domain' => Hostname::platformHostFor('mail'),
                'type' => DomainType::Alias->value,
            ])
            ->assertSessionHasErrors('domain');
    }

    #[Test]
    public function organisation_branding_is_resolved_from_the_active_tenant(): void
    {
        $organization = Organization::factory()->create(['name' => 'UPSA Demo']);
        $organization->branding()->update([
            'display_name' => 'UPSA Workspace',
            'login_heading' => 'Welcome to UPSA',
            'primary_color' => '#123456',
            'published_at' => now(),
        ]);
        app(BrandingService::class)->forget($organization);

        $this->fromTenant($organization)->get('/login')
            ->assertOk()
            ->assertSee('Welcome to UPSA')
            ->assertSee('UPSA Workspace')
            ->assertSee('#123456', false);
    }

    #[Test]
    public function publishing_branding_invalidates_the_cache(): void
    {
        $admin = $this->createOrganizationAdmin();
        $service = app(BrandingService::class);
        $before = $service->for($admin->organization);

        $this->actingAs($admin)
            ->put(route('org.organizations.branding.update', $admin->organization), [
                'display_name' => 'Fictional Review Institute',
                'primary_color' => '#AA3300',
                'login_heading' => 'Review Institute sign-in',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('org.organizations.branding.publish', $admin->organization))
            ->assertRedirect();

        $after = $service->for($admin->organization->fresh());
        $this->assertSame('Fictional Review Institute', $after->displayName);
        $this->assertSame('#AA3300', $after->primary);
        $this->assertNotSame($before->displayName, $after->displayName);
    }

    #[Test]
    public function a_user_cannot_open_another_organisations_workspace_or_files(): void
    {
        Storage::fake('organizations');

        $epa = Organization::factory()->create(['name' => 'EPA Demo', 'type' => OrganizationType::PublicInstitution]);
        $mcc = Organization::factory()->create(['name' => 'Mobile Content Ltd', 'type' => OrganizationType::Business]);
        $epaAdmin = $this->createOrganizationAdmin($epa);
        $mccAdmin = $this->createOrganizationAdmin($mcc);

        Invitation::factory()->forOrganization($epa)->create(['email' => 'epa-only@example.test']);

        $this->actingAs($mccAdmin)->get(route('org.users.index'))->assertOk()->assertDontSee('epa-only@example.test');

        $file = UploadedFile::fake()->image('logo.png', 64, 64);
        $this->actingAs($epaAdmin)
            ->put(route('org.organizations.branding.update', $epa), ['logo' => $file])
            ->assertRedirect();
        $this->actingAs($epaAdmin)->post(route('org.organizations.branding.publish', $epa));

        $this->actingAs($mccAdmin)->get(route('branding.asset', ['slot' => 'logo']))->assertNotFound();
        $this->actingAs($epaAdmin)->get(route('branding.asset', ['slot' => 'logo']))->assertOk();
    }

    #[Test]
    public function tenant_aware_jobs_restore_and_clear_context(): void
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set(Organization::factory()->create());

        $job = new ProcessTenantMail($organization->getKey());
        $result = (new RestoreTenantContext)->handle($job, fn () => $job->handle(app(TenantContext::class)));

        $this->assertSame(1, $result);
        $this->assertFalse(app(TenantContext::class)->has());
    }

    #[Test]
    public function queued_tenant_jobs_carry_the_organisation_id(): void
    {
        Queue::fake();
        $organization = Organization::factory()->create();

        ProcessTenantMail::dispatch($organization->getKey());

        Queue::assertPushed(ProcessTenantMail::class, function (ProcessTenantMail $job) use ($organization): bool {
            return $job->organizationId === $organization->getKey();
        });
    }

    #[Test]
    public function sequential_requests_do_not_leak_tenant_context(): void
    {
        $epa = Organization::factory()->create(['name' => 'EPA Demo']);
        $upsa = Organization::factory()->create(['name' => 'UPSA Demo']);
        $epaAdmin = $this->createOrganizationAdmin($epa);
        $upsaAdmin = $this->createOrganizationAdmin($upsa);

        $this->actingAs($epaAdmin)->get(route('org.dashboard'))->assertOk()->assertSee('EPA Demo');
        $this->assertFalse(app(TenantContext::class)->has());

        $this->actingAs($upsaAdmin)->get(route('org.dashboard'))->assertOk()->assertSee('UPSA Demo');
        $this->assertFalse(app(TenantContext::class)->has());
    }

    #[Test]
    public function only_workspace_admins_may_manage_domains_and_branding(): void
    {
        $admin = $this->createOrganizationAdmin();
        $member = $this->createMember($admin->organization);
        $stranger = $this->createOrganizationAdmin();

        $this->actingAs($member)->get(route('org.organizations.domains', $admin->organization))->assertForbidden();
        $this->actingAs($member)->get(route('org.organizations.branding', $admin->organization))->assertForbidden();
        $this->actingAs($stranger)->get(route('org.organizations.domains', $admin->organization))->assertForbidden();

        $this->actingAs($admin)->get(route('org.organizations.domains', $admin->organization))->assertOk();
        $this->actingAs($admin)->get(route('org.organizations.branding', $admin->organization))
            ->assertOk()
            ->assertSee('Primary colour picker', false);
    }
}
