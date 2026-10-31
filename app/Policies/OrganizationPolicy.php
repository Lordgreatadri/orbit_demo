<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Tenant abilities (own organisation only)
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Platform abilities (explicit, audited, never derived from tenant roles)
    |--------------------------------------------------------------------------
    */

    public function viewAnyAsPlatform(User $user): bool
    {
        return $user->isPlatformUser() && $user->can('platform.organizations.view');
    }

    public function viewAsPlatform(User $user, Organization $organization): bool
    {
        return $this->viewAnyAsPlatform($user);
    }

    public function updateStatus(User $user, Organization $organization): bool
    {
        return $user->isPlatformUser() && $user->can('platform.organizations.manage');
    }

    /**
     * Domains, aliases, branding, and login presentation.
     */
    public function manageWorkspace(User $user, Organization $organization): bool
    {
        if ($user->isPlatformUser()) {
            return $user->can('platform.organizations.manage');
        }

        return $this->update($user, $organization);
    }
}
