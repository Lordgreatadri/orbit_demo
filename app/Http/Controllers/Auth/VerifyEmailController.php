<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->afterEmailVerified($request);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $this->afterEmailVerified($request);
    }

    private function afterEmailVerified(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user()->fresh();

        if ($user !== null && ! $user->identityVerified()) {
            return redirect()
                ->route('verification.notice')
                ->with('status', __('E-mail address confirmed. Confirm your phone to continue.'));
        }

        return redirect()->intended(route('app.dashboard', absolute: false).'?verified=1');
    }
}
