<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks suspended users and users of suspended/closed organisations from the
 * authenticated application, even if they still hold a valid session.
 */
class EnsureAccountIsOperational
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            // Re-read the organisation on every request so a suspension takes
            // effect immediately, even if the user instance is long-lived.
            $organization = $user->organization_id !== null
                ? $user->organization()->first()
                : null;

            if ($organization !== null) {
                $user->setRelation('organization', $organization);
            }

            $blocked = ! $user->isActive()
                || ($organization !== null && ! $organization->isOperational());

            if ($blocked) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('error', __('This account is not currently able to sign in. Contact your organisation administrator.'));
            }
        }

        return $next($request);
    }
}
