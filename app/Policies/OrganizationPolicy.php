<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization) && $user->can('organization.view');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization)
            && $organization->isOperational()
            && $user->can('organization.update');
    }
}
