<?php

namespace App\Policies;

use App\Models\SmsProvider;
use App\Models\User;

class SmsProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformUser() && $user->can('platform.settings.manage');
    }

    public function view(User $user, SmsProvider $provider): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SmsProvider $provider): bool
    {
        return $this->viewAny($user);
    }
}
