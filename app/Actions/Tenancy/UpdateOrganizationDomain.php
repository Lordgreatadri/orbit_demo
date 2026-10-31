<?php

namespace App\Actions\Tenancy;

use App\Models\OrganizationDomain;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\TrustHostList;
use Illuminate\Validation\ValidationException;

class UpdateOrganizationDomain
{
    public function deactivate(OrganizationDomain $domain, User $actor): void
    {
        if ($domain->is_primary) {
            throw ValidationException::withMessages(['domain' => __('Choose another primary hostname before deactivating this one.')]);
        }

        $domain->forceFill([
            'is_active' => false,
            'updated_by' => $actor->getKey(),
        ])->save();

        TrustHostList::forgetCustomDomains();

        Audit::log('organization.domain.deactivated', $domain, ['domain' => $domain->domain], $actor, $domain->organization_id);
    }

    public function activate(OrganizationDomain $domain, User $actor): void
    {
        if ($domain->type->requiresVerification() && ! $domain->is_verified) {
            throw ValidationException::withMessages(['domain' => __('Verify the custom domain before activating it.')]);
        }

        $domain->forceFill([
            'is_active' => true,
            'updated_by' => $actor->getKey(),
        ])->save();

        TrustHostList::forgetCustomDomains();

        Audit::log('organization.domain.activated', $domain, ['domain' => $domain->domain], $actor, $domain->organization_id);
    }

    public function delete(OrganizationDomain $domain, User $actor): void
    {
        if ($domain->is_primary) {
            throw ValidationException::withMessages(['domain' => __('The primary hostname cannot be removed.')]);
        }

        $host = $domain->domain;
        $organizationId = $domain->organization_id;
        $domain->delete();

        TrustHostList::forgetCustomDomains();

        Audit::log('organization.domain.deleted', $actor, ['domain' => $host], $actor, $organizationId);
    }
}
