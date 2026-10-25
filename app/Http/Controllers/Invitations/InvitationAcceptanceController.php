<?php

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\AcceptInvitationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guest-facing acceptance of an invitation link. This is the only account
 * creation path besides organisation registration.
 */
class InvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token, AcceptInvitation $acceptInvitation): View
    {
        $invitation = $acceptInvitation->findPending($token);

        if ($invitation === null) {
            return view('auth.invitation-invalid');
        }

        return view('auth.invitation-accept', [
            'invitation' => $invitation,
            'token' => $token,
            'roleLabel' => config('orbit.roles.tenant.'.$invitation->role, $invitation->role),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse|View
    {
        $invitation = $acceptInvitation->findPending($token);

        if ($invitation === null) {
            return view('auth.invitation-invalid');
        }

        $user = $acceptInvitation->handle($invitation, $request->safe()->only(['first_name', 'last_name', 'phone', 'password']));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()
            ->route('app.dashboard')
            ->with('status', __('Welcome to :org. Your account has been created.', ['org' => $invitation->organization->name]));
    }
}
