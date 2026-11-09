<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ResendInvitation
{
    /**
     * Issue a fresh single-use link for the same address and role.
     * The previous link stops working because only a hash is stored.
     */
    public function handle(User $actor, Invitation $invitation): Invitation
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation can no longer be resent.'),
            ]);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'invitation' => __('An account with this e-mail address already exists.'),
            ]);
        }

        [$rawToken, $hash] = Invitation::generateToken();

        $invitation = DB::transaction(function () use ($invitation, $actor, $hash): Invitation {
            $invitation->forceFill([
                'token_hash' => $hash,
                'expires_at' => now()->addDays((int) config('orbit.invitation_ttl_days', 7)),
            ])->save();

            Audit::log('invitation.resent', $invitation, ['role' => $invitation->role], $actor, $invitation->organization_id);

            return $invitation;
        });

        Notification::route('mail', $invitation->email)->notify(new InvitationNotification($invitation, $rawToken));

        return $invitation;
    }
}
