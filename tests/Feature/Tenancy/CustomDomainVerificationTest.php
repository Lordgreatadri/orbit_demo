<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DomainType;
use App\Integrations\Dns\ArrayDnsLookup;
use App\Models\OrganizationDomain;
use App\Tenancy\TrustHostList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomDomainVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_custom_domain_activates_only_after_the_txt_record_matches(): void
    {
        $admin = $this->createOrganizationAdmin();
        $dns = app(ArrayDnsLookup::class);

        $this->actingAs($admin)
            ->post(route('org.organizations.domains.store', $admin->organization), [
                'domain' => 'attendance.mobilecontent.test',
                'type' => DomainType::CustomDomain->value,
            ])
            ->assertSessionHasNoErrors();

        $domain = OrganizationDomain::query()->where('domain', 'attendance.mobilecontent.test')->sole();
        $this->assertFalse($domain->is_verified);
        $this->assertFalse($domain->is_active);

        $this->actingAs($admin)
            ->post(route('org.organizations.domains.verify', [$admin->organization, $domain]))
            ->assertSessionHasErrors('domain');

        $dns->put('_orbit-verify.attendance.mobilecontent.test', [$domain->verification_token]);

        $this->actingAs($admin)
            ->post(route('org.organizations.domains.verify', [$admin->organization, $domain]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($domain->fresh()->is_verified);
        $this->assertTrue($domain->fresh()->is_active);
    }

    #[Test]
    public function activating_or_deactivating_evicts_the_trusted_host_cache(): void
    {
        $admin = $this->createOrganizationAdmin();
        $domain = OrganizationDomain::factory()
            ->for($admin->organization)
            ->custom('attendance.mobilecontent.test', verified: true)
            ->create(['is_active' => false]);

        Cache::put(TrustHostList::CUSTOM_DOMAINS_CACHE_KEY, ['stale.example.test'], 300);

        $this->actingAs($admin)
            ->post(route('org.organizations.domains.activate', [$admin->organization, $domain]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertFalse(Cache::has(TrustHostList::CUSTOM_DOMAINS_CACHE_KEY));
        $this->assertTrue($domain->fresh()->is_active);

        Cache::put(TrustHostList::CUSTOM_DOMAINS_CACHE_KEY, ['attendance.mobilecontent.test'], 300);

        $this->actingAs($admin)
            ->post(route('org.organizations.domains.deactivate', [$admin->organization, $domain]))
            ->assertSessionHasNoErrors();

        $this->assertFalse(Cache::has(TrustHostList::CUSTOM_DOMAINS_CACHE_KEY));
    }
}
