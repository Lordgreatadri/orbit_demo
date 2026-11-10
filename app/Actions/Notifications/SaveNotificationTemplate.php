<?php

namespace App\Actions\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Support\Audit;

final class SaveNotificationTemplate
{
    public function __invoke(User $actor, string $event, string $subject, string $body): void
    {
        $where = [
            'organization_id' => $actor->organization_id,
            'event' => $event,
            'channel' => 'all',
        ];

        if ($subject === '' && $body === '') {
            NotificationTemplate::withoutTenantScope()->where($where)->delete();
            Audit::log('notifications.template_cleared', null, [
                'event' => $event,
                'channel' => 'all',
            ], $actor);

            return;
        }

        NotificationTemplate::withoutTenantScope()->updateOrCreate($where, [
            'subject' => $subject,
            'body' => $body,
        ]);

        Audit::log('notifications.template_updated', null, [
            'event' => $event,
            'channel' => 'all',
        ], $actor);
    }
}
