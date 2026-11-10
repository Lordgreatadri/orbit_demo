<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['organization_id', 'name', 'code'])]
class StaffCategory extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }
}
