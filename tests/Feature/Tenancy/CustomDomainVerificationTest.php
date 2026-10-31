<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DomainType;
use App\Integrations\Dns\ArrayDnsLookup;
use App\Models\OrganizationDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
