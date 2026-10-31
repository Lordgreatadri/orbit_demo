<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\RestoreTenantContext;

trait IsTenantAware
{
    public int $organizationId;

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RestoreTenantContext];
    }
}
