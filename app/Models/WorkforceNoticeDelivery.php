<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'user_id', 'dedupe_key', 'channel'])]
class WorkforceNoticeDelivery extends Model
{
    use BelongsToOrganization;
}
