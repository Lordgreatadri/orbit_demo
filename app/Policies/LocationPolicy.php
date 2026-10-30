<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isPlatformUser() && ($user->can('locations.view') || $user->can('locations.manage'));
    }

    public function create(User $user): bool
    {
        return ! $user->isPlatformUser() && $user->can('locations.manage');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->belongsToOrganization($location->organization_id) && $user->can('locations.manage');
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }
}
