<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Audit entry with an explicit tenant column for efficient per-organisation filtering.
 */
class Activity extends SpatieActivity
{
    use HasPublicUuid;
}
