<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Invitations\CreateInvitation;
use App\Actions\Invitations\ResendInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Models\Invitation;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, CreateInvitation $createInvitation): RedirectResponse
    {
        $invitation = $createInvitation->handle(
            $request->user(),
            $request->validated('email'),
            $request->validated('role'),
        );

        return redirect()
            ->route('org.users.index')
            ->with('status', __('Invitation sent to :email.', ['email' => $invitation->email]));
    }

    /**
     * Revoke a pending invitation. Route binding is tenant-scoped, so another
     * organisation's invitation resolves to 404; the policy adds a second check.
     */
    public function destroy(Request $request, Invitation $invitation): RedirectResponse
    {
        $this->authorize('delete', $invitation);

        if ($invitation->isPending()) {
            $invitation->forceFill(['revoked_at' => now()])->save();

            Audit::log('invitation.revoked', $invitation, [], $request->user(), $invitation->organization_id);
        }

        return redirect()
            ->route('org.users.index')
            ->with('status', __('Invitation for :email revoked.', ['email' => $invitation->email]));
    }

    /**
     * Send a new link for a pending or expired invitation. Accepted and
     * revoked invitations stay closed. The previous link stops working.
     */
    public function resend(Request $request, Invitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        $this->authorize('resend', $invitation);

        try {
            $resendInvitation->handle($request->user(), $invitation);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            return back()->with('error', is_string($message) ? $message : __('This invitation can no longer be resent.'));
        }

        return redirect()
            ->route('org.users.index')
            ->with('status', __('Invitation resent to :email. The new link replaces the previous one.', ['email' => $invitation->email]));
    }
}
