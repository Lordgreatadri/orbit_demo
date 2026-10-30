<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Audit entry with an explicit tenant column for efficient per-organisation filtering.
 */
class Activity extends SpatieActivity
{
    use HasPublicUuid;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Short human label for the event key, e.g. "user.roles_updated" => "User · roles updated".
     */
    public function eventLabel(): string
    {
        $event = (string) ($this->event ?? $this->description);

        if (! str_contains($event, '.')) {
            return ucfirst(str_replace('_', ' ', $event));
        }

        [$module, $action] = explode('.', $event, 2);

        return ucfirst(str_replace('_', ' ', $module)).' · '.str_replace(['_', '.'], ' ', $action);
    }
}
