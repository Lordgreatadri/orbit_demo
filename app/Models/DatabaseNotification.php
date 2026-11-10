<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Numeric primary key, with Laravel's notification id stored in uuid.
 * The database channel writes that id into `id` and does not know about uuid.
 * The organisation and event are stamped here so the inbox can be scoped
 * and filtered without reading the JSON payload.
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    public $incrementing = true;

    protected $keyType = 'int';

    protected static function booted(): void
    {
        static::creating(function (self $notification): void {
            $attributes = $notification->getAttributes();
            $incoming = $attributes['id'] ?? null;

            if (is_string($incoming) && $incoming !== '' && ! ctype_digit($incoming)) {
                unset($attributes['id']);
                $attributes['uuid'] = $incoming;
            }

            if (empty($attributes['organization_id'])) {
                $organizationId = self::organizationIdFor($notification);

                if ($organizationId !== null) {
                    $attributes['organization_id'] = $organizationId;
                }
            }

            if (empty($attributes['event'])) {
                $event = self::eventFrom($attributes['data'] ?? null);

                if ($event !== null) {
                    $attributes['event'] = $event;
                }
            }

            $notification->setRawAttributes($attributes);
        });
    }

    private static function organizationIdFor(self $notification): ?int
    {
        $notifiable = $notification->notifiable;

        if ($notifiable instanceof User && $notifiable->organization_id !== null) {
            return (int) $notifiable->organization_id;
        }

        return null;
    }

    private static function eventFrom(mixed $data): ?string
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (! is_array($data) || ! isset($data['event']) || ! is_scalar($data['event'])) {
            return null;
        }

        $event = trim((string) $data['event']);

        return $event === '' ? null : mb_substr($event, 0, 64);
    }
}
