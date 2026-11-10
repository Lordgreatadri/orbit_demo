<?php

namespace App\Jobs\Middleware;

use App\Models\Organization;
use App\Tenancy\TenantContext;

/**
 * Queue middleware: run the job in its organisation, then put back whatever
 * tenant was current before the job, including when the job fails.
 */
class RestoreTenantContext
{
    public function handle(object $job, callable $next): mixed
    {
        $context = app(TenantContext::class);
        $previous = $context->organization();
        $organizationId = $job->organizationId ?? null;

        if (is_int($organizationId) || (is_string($organizationId) && is_numeric($organizationId))) {
            $organization = Organization::query()->find((int) $organizationId);

            if ($organization) {
                $context->set($organization);
            }
        }

        try {
            return $next($job);
        } finally {
            $context->set($previous);
        }
    }
}
