<?php

namespace App\Actions\Notifications;

use App\Models\CommunicationPolicy;
use App\Models\User;
use App\Support\Audit;

final class SaveCommunicationPolicy
{
    /**
     * @param  array<string, bool>  $flags
     */
    public function __invoke(User $actor, array $flags): void
    {
        $policy = CommunicationPolicy::withoutTenantScope()->updateOrCreate(
            ['organization_id' => $actor->organization_id],
            $flags,
        );

        Audit::log('notifications.policy_updated', $policy, $flags, $actor);
    }
}
