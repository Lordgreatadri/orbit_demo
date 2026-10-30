<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformUser()
            && ($user->can('platform.roles.manage') || $user->can('platform.users.view'));
    }

    public function create(User $user): bool
    {
        return $user->isPlatformUser() && $user->can('platform.roles.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->create($user) && ! $permission->is_system;
    }
}
