<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Numeric primary key, with Laravel's notification id stored in uuid.
 * The database channel writes that id into `id` and does not know about uuid.
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

            if (! is_string($incoming) || $incoming === '' || ctype_digit($incoming)) {
                return;
            }

            unset($attributes['id']);
            $attributes['uuid'] = $incoming;
            $notification->setRawAttributes($attributes);
        });
    }
}
