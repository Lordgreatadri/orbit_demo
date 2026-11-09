<?php

namespace App\Models;

use App\Enums\WorkArrangementKind;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'name', 'code', 'kind', 'is_active'])]
class WorkArrangement extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => WorkArrangementKind::class,
            'is_active' => 'boolean',
        ];
    }
}
