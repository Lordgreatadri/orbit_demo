<?php

namespace App\Http\Controllers\Auth;

use App\Auth\PhoneVerificationCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPhoneVerificationRequest;
use App\Http\Requests\Auth\UpdateVerificationPhoneRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function send(Request $request, PhoneVerificationCode $codes): RedirectResponse
    {
        $codes->issue($request->user());

        return back()->with('status', __('A verification code was issued for your phone. Your technical team can read it from the application log until text messaging is connected.'));
    }

    public function confirm(ConfirmPhoneVerificationRequest $request, PhoneVerificationCode $codes): RedirectResponse
    {
        $codes->confirm($request->user(), $request->string('code')->toString());

        $user = $request->user()->fresh();

        if ($user->identityVerified()) {
            return redirect()->intended(route('app.dashboard', absolute: false));
        }

        return back()->with('status', __('Phone number confirmed. Confirm your e-mail to continue.'));
    }

    public function update(UpdateVerificationPhoneRequest $request, PhoneVerificationCode $codes): RedirectResponse
    {
        $user = $request->user();
        $phone = $request->string('phone')->toString();
        $changed = $user->phone !== $phone;

        $user->forceFill([
            'phone' => $phone,
            'phone_verified_at' => $changed ? null : $user->phone_verified_at,
        ])->save();

        $codes->issue($user->fresh());

        return back()->with('status', __('A verification code was issued for :phone. Your technical team can read it from the application log until text messaging is connected.', ['phone' => $phone]));
    }
}
