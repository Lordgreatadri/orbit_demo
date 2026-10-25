<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class CreateInvitation
{
    /**
     * Create a single-use, expiring invitation into the inviter's own organisation.
     * The tenant is always the inviter's organisation — never client input.
     */
    public function handle(User $inviter, string $email, string $role): Invitation
    {
        $organization = $inviter->organization;

        if ($organization === null) {
            throw ValidationException::withMessages(['email' => __('Only organisation accounts can invite members.')]);
        }

        if (! in_array($role, config('orbit.invitable_roles', []), true)) {
            throw ValidationException::withMessages(['role' => __('That role cannot be granted by invitation.')]);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('An account with this e-mail address already exists.')]);
        }

        if (Invitation::query()->where('organization_id', $organization->getKey())->where('email', $email)->pending()->exists()) {
            throw ValidationException::withMessages(['email' => __('A pending invitation already exists for this e-mail address.')]);
        }

        [$rawToken, $hash] = Invitation::generateToken();

        $invitation = DB::transaction(function () use ($organization, $inviter, $email, $role, $hash): Invitation {
            $invitation = Invitation::create([
                'organization_id' => $organization->getKey(),
                'invited_by_id' => $inviter->getKey(),
                'email' => $email,
                'role' => $role,
                'token_hash' => $hash,
                'expires_at' => now()->addDays((int) config('orbit.invitation_ttl_days', 7)),
            ]);

            Audit::log('invitation.created', $invitation, ['role' => $role], $inviter, $organization->getKey());

            return $invitation;
        });

        Notification::route('mail', $invitation->email)->notify(new InvitationNotification($invitation, $rawToken));

        return $invitation;
    }
}
