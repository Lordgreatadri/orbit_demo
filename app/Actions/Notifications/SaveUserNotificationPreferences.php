<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\Audit;
use App\Workforce\WorkforceEvents;

final class SaveUserNotificationPreferences
{
    /**
     * @param  array<string, array<string, mixed>>  $categories
     */
    public function __invoke(User $user, array $categories): void
    {
        $saved = [];

        foreach (array_keys(WorkforceEvents::categories()) as $category) {
            $row = $categories[$category] ?? [];
            $choice = [
                'mail' => filter_var($row['mail'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'database' => filter_var($row['database'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sms' => filter_var($row['sms'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            UserNotificationPreference::withoutTenantScope()->updateOrCreate(
                ['user_id' => $user->getKey(), 'category' => $category],
                ['organization_id' => $user->organization_id, ...$choice],
            );
            $saved[$category] = $choice;
        }

        Audit::log('notifications.preferences_updated', null, ['categories' => $saved], $user);
    }
}
