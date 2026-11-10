<?php

namespace App\Models;

use App\Enums\NonWorkingKind;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'observed_on', 'name', 'kind'])]
class NonWorkingDay extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'kind' => NonWorkingKind::class,
        ];
    }
}
