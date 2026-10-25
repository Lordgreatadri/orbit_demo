<?php

namespace App\Tenancy;

use App\Models\Organization;

/**
 * Request/job-scoped holder for the current tenant.
 *
 * The context is only ever populated from the authenticated user (see
 * SetTenantContext middleware) or by an explicit, audited call inside a job.
 * It must never be populated from browser-supplied input.
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
