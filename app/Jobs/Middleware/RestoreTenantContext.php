<?php

namespace App\Jobs\Middleware;

use App\Models\Organization;
use App\Tenancy\TenantContext;

/**
 * Queue middleware: restore tenant context from the job's organizationId
 * and always clear it afterwards, including when the job fails.
 */
class RestoreTenantContext
{
    public function handle(object $job, callable $next): mixed
    {
        $context = app(TenantContext::class);
        $context->clear();

        $organizationId = $job->organizationId ?? null;

        if (is_int($organizationId) || is_numeric($organizationId)) {
            $organization = Organization::query()->find((int) $organizationId);
            if ($organization) {
                $context->set($organization);
            }
        }

        try {
            return $next($job);
        } finally {
            $context->clear();
        }
    }
}
