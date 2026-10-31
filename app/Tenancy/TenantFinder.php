<?php

namespace App\Tenancy;

use App\Enums\DomainType;
use App\Enums\OrganizationStatus;
use App\Models\OrganizationDomain;
use Illuminate\Http\Request;

/**
 * Resolves a request hostname against the domain registry.
 * The organisation slug is never used as a hostname fallback.
 */
class TenantFinder
{
    public function resolve(Request $request): TenantResolution
    {
        $host = Hostname::normalize($request->getHost());

        if ($host === '') {
            return TenantResolution::unknown('');
        }

        if (Hostname::isCentral($host)) {
            return TenantResolution::central($host);
        }

        $domain = OrganizationDomain::query()
            ->where('domain', $host)
            ->with('organization')
            ->first();

        if ($domain === null) {
            return TenantResolution::unknown($host);
        }

        if ($domain->type === DomainType::CustomDomain && ! $domain->is_verified) {
            return TenantResolution::unverified($host, $domain);
        }

        if (! $domain->is_active) {
            return TenantResolution::inactive($host, $domain, $domain->organization);
        }

        $organization = $domain->organization;

        if ($organization === null || $organization->status === OrganizationStatus::Closed) {
            return TenantResolution::inactive($host, $domain, $organization);
        }

        return TenantResolution::tenant($host, $domain, $organization);
    }
}
