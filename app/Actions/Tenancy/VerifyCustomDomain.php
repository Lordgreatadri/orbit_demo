<?php

namespace App\Actions\Tenancy;

use App\Contracts\DnsLookup;
use App\Enums\DomainType;
use App\Models\OrganizationDomain;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TrustHostList;
use Illuminate\Validation\ValidationException;

class VerifyCustomDomain
{
    public function __construct(private readonly DnsLookup $dns) {}

    public function handle(OrganizationDomain $domain, User $actor): void
    {
        if ($domain->type !== DomainType::CustomDomain) {
            throw ValidationException::withMessages(['domain' => __('Only custom domains need DNS verification.')]);
        }

        $name = '_orbit-verify.'.$domain->domain;
        $records = $this->dns->txt($name);

        if ($domain->verification_token === null || ! in_array($domain->verification_token, $records, true)) {
            throw ValidationException::withMessages([
                'domain' => __('Add a TXT record on :name with the verification token, then try again.', ['name' => $name]),
            ]);
        }

        $domain->forceFill([
            'is_verified' => true,
            'is_active' => true,
            'verified_at' => now(),
            'updated_by' => $actor->getKey(),
        ])->save();

        TrustHostList::forgetCustomDomains();

        Audit::log('organization.domain.verified', $domain, ['domain' => $domain->domain], $actor, $domain->organization_id);
    }
}
