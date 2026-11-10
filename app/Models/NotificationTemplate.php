<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'event', 'channel', 'subject', 'body'])]
class NotificationTemplate extends Model
{
    use BelongsToOrganization;
}
