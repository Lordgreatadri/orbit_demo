<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Plug for a future push provider. The default binding records nothing and
 * does not call an external service.
 */
interface PushNotifier
{
    public function send(User $user, string $title, string $body): void;
}
