<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformUser() && $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformUser()
            && $user->organization?->isActive() === true
            && $user->can('users.invite');
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->belongsToOrganization($invitation->organization_id) && $user->can('users.invite');
    }
}
