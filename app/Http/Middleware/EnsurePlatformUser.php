<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform console (/admin/*): platform accounts holding `platform.access`.
 * Tenant roles can never satisfy this check, whatever permissions they hold.
 */
class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isPlatformUser() || ! $user->can('platform.access')) {
            abort(403, __('The platform console is restricted to authorised platform accounts.'));
        }

        return $next($request);
    }
}
