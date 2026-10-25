<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasPublicUuid;
}
