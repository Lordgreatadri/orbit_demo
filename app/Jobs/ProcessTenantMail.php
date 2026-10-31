<?php

namespace App\Jobs;

use App\Jobs\Concerns\IsTenantAware;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reference tenant-aware job used by tests and as a pattern for later mail/SMS.
 * Carries organizationId on the payload and restores TenantContext in middleware.
 */
class ProcessTenantMail implements ShouldQueue
{
    use IsTenantAware;
    use Queueable;

    public function __construct(int $organizationId)
    {
        $this->organizationId = $organizationId;
    }

    public function handle(TenantContext $context): int
    {
        $id = $context->id();

        if ($id === null || $id !== $this->organizationId) {
            return 0;
        }

        return (int) Organization::query()->whereKey($this->organizationId)->count();
    }
}
