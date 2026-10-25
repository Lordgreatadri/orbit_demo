<?php

namespace App\Actions\Invitations;

use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    /**
     * Resolve a pending invitation from its raw token, or null.
     * Called from guest context: no tenant scope is active, so the lookup is global.
     */
    public function findPending(string $rawToken): ?Invitation
    {
        if ($rawToken === '' || strlen($rawToken) > 128) {
            return null;
        }

        return Invitation::query()
            ->with('organization')
            ->where('token_hash', Invitation::hashToken($rawToken))
            ->pending()
            ->first();
    }

    /**
     * @param  array{first_name: string, last_name: string, phone?: string|null, password: string}  $data
     */
    public function handle(Invitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            // Re-check inside the transaction with a row lock to defeat replays.
            $locked = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->isPending()) {
                throw ValidationException::withMessages(['token' => __('This invitation is no longer valid.')]);
            }

            if (User::query()->where('email', $locked->email)->exists()) {
                throw ValidationException::withMessages(['token' => __('An account with this e-mail address already exists. Please sign in.')]);
            }

            $user = new User([
                'organization_id' => $locked->organization_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $locked->email,
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => UserStatus::Active,
                'terms_accepted_at' => now(),
                'terms_version' => config('orbit.terms_version'),
            ]);

            // The invitation link proves control of the mailbox; this column is
            // deliberately not mass-assignable.
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->assignRole($locked->role);

            $locked->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->getKey(),
            ])->save();

            Audit::log('invitation.accepted', $locked, ['role' => $locked->role], $user, $locked->organization_id);

            return $user;
        });
    }
}
