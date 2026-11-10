<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use App\Workforce\WorkforceEvents;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'event', 'mail', 'sms', 'database', 'push'])]
class NotificationPreference extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail' => 'boolean',
            'sms' => 'boolean',
            'database' => 'boolean',
            'push' => 'boolean',
        ];
    }

    /**
     * Organisation channels, then the tenant master switches, then a personal
     * opt-out. A mandatory event ignores the personal opt-out. A person from
     * another organisation receives nothing.
     *
     * @return array{mail: bool, sms: bool, database: bool, push: bool}
     */
    public static function channelsFor(Organization $organization, string $event, ?User $user = null): array
    {
        $closed = ['mail' => false, 'sms' => false, 'database' => false, 'push' => false];

        if ($user !== null && (int) $user->organization_id !== (int) $organization->getKey()) {
            return $closed;
        }

        $definition = WorkforceEvents::definition($event);

        if ($definition === null) {
            return $closed;
        }

        $policy = CommunicationPolicy::forOrganization($organization);

        if (! $policy->allows($definition['category'], $definition['reminder'])) {
            return $closed;
        }

        $row = static::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->where('event', $event)
            ->first();

        $channels = $row === null
            ? ['mail' => true, 'sms' => false, 'database' => true, 'push' => false]
            : [
                'mail' => $row->mail,
                'sms' => $row->sms,
                'database' => $row->database,
                'push' => $row->push,
            ];

        $channels['mail'] = $channels['mail'] && $policy->email_enabled;
        $channels['sms'] = $channels['sms'] && $policy->sms_enabled;
        $channels['database'] = $channels['database'] && $policy->in_app_enabled;

        if ($user === null || $definition['mandatory']) {
            return $channels;
        }

        $preference = UserNotificationPreference::withoutTenantScope()
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organization->getKey())
            ->where('category', $definition['category'])
            ->first();

        if ($preference === null) {
            return $channels;
        }

        $channels['mail'] = $channels['mail'] && $preference->mail;
        $channels['sms'] = $channels['sms'] && $preference->sms;
        $channels['database'] = $channels['database'] && $preference->database;

        return $channels;
    }
}
