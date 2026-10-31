<?php

namespace App\Tenancy;

use App\Models\Organization;

/**
 * Request/job-scoped holder for the current tenant.
 *
 * Populated from the request hostname (IdentifyTenant) or restored from a
 * job payload (RestoreTenantContext). Never from a user-supplied parameter.
 */
class TenantContext
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function clear(): void
    {
        $this->organization = null;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function organization(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->getKey();
    }
}
