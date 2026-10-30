<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Organisation workspace (/org/*): only accounts that belong to an organisation.
 * Platform accounts never enter tenant screens through this door (ADR-0009 §2);
 * they use the audited platform console instead.
 */
class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isPlatformUser()) {
            abort(403, __('This area is for organisation accounts.'));
        }

        return $next($request);
    }
}
