<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Derives the tenant from the authenticated user only (ADR-0002/0009).
 * Always resets the context first so nothing leaks between requests that
 * share a container (tests, long-running workers).
 */
class SetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->clear();

        $user = $request->user();

        if ($user instanceof User && $user->organization_id !== null) {
            $this->context->set($user->organization);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->clear();
    }
}
