<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
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
     * @return array{mail: bool, sms: bool, database: bool, push: bool}
     */
    public static function channelsFor(Organization $organization, string $event): array
    {
        $row = static::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->where('event', $event)
            ->first();

        if ($row === null) {
            return ['mail' => true, 'sms' => false, 'database' => true, 'push' => false];
        }

        return [
            'mail' => $row->mail,
            'sms' => $row->sms,
            'database' => $row->database,
            'push' => $row->push,
        ];
    }
}
