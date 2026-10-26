<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('platform_super_admin')) {
            return true;
        }

        return ! $user->isPlatformUser() && $user->can('members.view');
    }

    public function view(User $user, User $member): bool
    {
        if ($user->is($member)) {
            return true;
        }

        return $member->organization_id !== null
            && $user->belongsToOrganization($member->organization_id)
            && $user->can('members.view');
    }
}
