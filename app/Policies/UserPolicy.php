<?php

namespace App\Policies;

use App\Access\GrantResolver;
use App\Models\User;

/**
 * Login accounts. Organisation administrators manage accounts in their own
 * organisation; platform operators use the explicit platform.users.* permissions.
 */
class UserPolicy
{
    public function __construct(private readonly GrantResolver $resolver) {}

    public function viewAny(User $user): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can('platform.users.view');
        }

        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can('platform.users.view');
        }

        return $target->organization_id !== null
            && $user->belongsToOrganization((int) $target->organization_id)
            && $user->can('users.view');
    }

    /**
     * Change roles, direct permissions, or status.
     */
    public function manageAccess(User $user, User $target): bool
    {
        return $this->resolver->canManage($user, $target);
    }

    /**
     * Provision a platform (non-organisation) account.
     */
    public function createPlatformAccount(User $user): bool
    {
        return $user->isPlatformUser() && $user->can('platform.users.manage');
    }
}
