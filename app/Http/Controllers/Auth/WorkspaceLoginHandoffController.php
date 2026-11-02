<?php

namespace App\Http\Controllers\Auth;

use App\Auth\WorkspaceLoginHandoff;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceLoginHandoffController extends Controller
{
    public function __invoke(Request $request, string $token, WorkspaceLoginHandoff $handoff, TenantContext $context): RedirectResponse
    {
        $result = $handoff->pull($token, $request->getHost(), $context->id());

        if ($result === null) {
            return redirect()->route('login')->withErrors([
                'email' => __('This sign-in link has expired. Enter your password again.'),
            ]);
        }

        Auth::login($result['user'], $result['remember']);

        $request->session()->regenerate();

        $result['user']->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('org.dashboard', absolute: false));
    }
}
