<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformUser() && ($user->can('staff.view') || $user->can('departments.manage'));
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformUser() && $user->can('departments.manage');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->belongsToOrganization($department->organization_id) && $user->can('departments.manage');
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }
}
