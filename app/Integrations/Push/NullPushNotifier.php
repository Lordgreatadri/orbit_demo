<?php

namespace App\Integrations\Push;

use App\Contracts\PushNotifier;
use App\Models\User;

final class NullPushNotifier implements PushNotifier
{
    public function send(User $user, string $title, string $body): void
    {
        // No push provider is configured. Mail, database, and SMS remain the live channels.
    }
}
