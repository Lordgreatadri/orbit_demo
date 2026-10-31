<?php

namespace App\Actions\Tenancy;

use App\Enums\DomainType;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\Hostname;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterOrganizationDomain
{
    public function handle(Organization $organization, string $rawHost, DomainType $type, User $actor, bool $makePrimary = false): OrganizationDomain
    {
        $host = Hostname::normalize($rawHost);

        if ($host === '' || Hostname::isCentral($host)) {
            throw ValidationException::withMessages(['domain' => __('That hostname is reserved for the ORBIT platform.')]);
        }

        $subdomain = Hostname::platformSubdomain($host);

        if ($type === DomainType::CustomDomain) {
            if ($subdomain !== null) {
                throw ValidationException::withMessages(['domain' => __('A custom domain must be an organisation-owned hostname, not a platform subdomain.')]);
            }
        } else {
            if ($subdomain === null) {
                throw ValidationException::withMessages(['domain' => __('Aliases and platform subdomains must end with :base.', ['base' => Hostname::baseDomain()])]);
            }
            if (Hostname::isReservedSubdomain($subdomain)) {
                throw ValidationException::withMessages(['domain' => __('The subdomain :label is reserved.', ['label' => $subdomain])]);
            }
        }

        if (OrganizationDomain::query()->where('domain', $host)->exists()) {
            throw ValidationException::withMessages(['domain' => __('That hostname is already registered.')]);
        }

        $verified = $type !== DomainType::CustomDomain;

        $domain = $organization->domains()->create([
            'domain' => $host,
            'type' => $type,
            'is_primary' => false,
            'is_active' => $verified,
            'is_verified' => $verified,
            'verified_at' => $verified ? now() : null,
            'verification_token' => $type === DomainType::CustomDomain ? Str::lower(Str::random(40)) : null,
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);

        if ($makePrimary && $verified) {
            app(MakeDomainPrimary::class)->handle($domain, $actor);
            $domain->refresh();
        }

        Audit::log('organization.domain.registered', $domain, [
            'domain' => $host,
            'type' => $type->value,
        ], $actor, $organization->getKey());

        return $domain;
    }
}
