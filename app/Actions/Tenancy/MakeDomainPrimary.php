<?php

namespace App\Actions\Tenancy;

use App\Models\OrganizationDomain;
use App\Models\User;
use App\Support\Audit;
use App\Tenancy\BrandingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MakeDomainPrimary
{
    public function handle(OrganizationDomain $domain, User $actor): void
    {
        if (! $domain->isResolvable()) {
            throw ValidationException::withMessages(['domain' => __('Only an active, verified hostname can be the primary workspace URL.')]);
        }

        DB::transaction(function () use ($domain, $actor): void {
            $domain->organization->domains()->where('id', '!=', $domain->getKey())->update(['is_primary' => false]);
            $domain->forceFill([
                'is_primary' => true,
                'is_active' => true,
                'updated_by' => $actor->getKey(),
            ])->save();
        });

        app(BrandingService::class)->forget($domain->organization);

        Audit::log('organization.domain.primary', $domain, ['domain' => $domain->domain], $actor, $domain->organization_id);
    }
}
