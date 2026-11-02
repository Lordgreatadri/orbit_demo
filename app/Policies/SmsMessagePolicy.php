<?php

namespace App\Policies;

use App\Models\SmsMessage;
use App\Models\User;

class SmsMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPlatformUser() && $user->can('platform.settings.manage');
    }

    public function view(User $user, SmsMessage $message): bool
    {
        return $this->viewAny($user);
    }
}
