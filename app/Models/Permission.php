<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasPublicUuid;
}
