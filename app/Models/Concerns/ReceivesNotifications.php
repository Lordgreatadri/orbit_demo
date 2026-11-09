<?php

namespace App\Models\Concerns;

use App\Models\DatabaseNotification;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;

/**
 * Database notifications use a numeric primary key. Laravel's channel still
 * generates a UUID and writes it to `id`; {@see DatabaseNotification} moves
 * that value into `uuid` before insert.
 */
trait ReceivesNotifications
{
    use Notifiable {
        notifications as frameworkNotifications;
    }

    /**
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }
}
