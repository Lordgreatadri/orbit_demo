<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Roles are global (teams disabled, ADR-0009). Only the platform console may
 * change them; organisation administrators get a read-only view of the tenant
 * roles so they understand what each grants.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can('platform.roles.manage') || $user->can('platform.users.view');
        }

        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->isPlatformUser() && $user->can('platform.roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->create($user) && ! $role->is_system;
    }
}
