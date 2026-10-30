<?php

namespace App\Policies;

use App\Models\StaffMember;
use App\Models\User;

class StaffMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformUser() && $user->can('staff.view');
    }

    public function view(User $user, StaffMember $staff): bool
    {
        return $user->belongsToOrganization($staff->organization_id) && $user->can('staff.view');
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformUser()
            && $user->organization?->isOperational() === true
            && $user->can('staff.manage');
    }

    public function update(User $user, StaffMember $staff): bool
    {
        return $user->belongsToOrganization($staff->organization_id) && $user->can('staff.manage');
    }

    public function delete(User $user, StaffMember $staff): bool
    {
        return $this->update($user, $staff);
    }
}
