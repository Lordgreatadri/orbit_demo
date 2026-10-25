<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Events\Verified;

/**
 * When the responsible contact (an organisation administrator) verifies their
 * e-mail, the organisation moves from "pending verification" to "active".
 */
class ActivateOrganizationOnVerification
{
    public function handle(Verified $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->organization === null) {
            return;
        }

        if (! $user->hasRole('organization_admin')) {
            return;
        }

        $organization = $user->organization;

        if ($organization->isActive()) {
            return;
        }

        $organization->markVerified();

        Audit::log('organization.verified', $organization, [], $user, $organization->getKey());
    }
}
