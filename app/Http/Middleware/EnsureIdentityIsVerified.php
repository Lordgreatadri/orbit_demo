<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * E-mail and phone must both be confirmed before the workspace opens.
 * The verification screen shows the two checks together.
 */
class EnsureIdentityIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->identityVerified()) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
