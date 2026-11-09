<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'department_id',
    'staff_category_id',
    'location_id',
    'work_arrangement_id',
    'created_by',
    'name',
    'starts_at',
    'ends_at',
    'crosses_midnight',
    'weekdays',
    'effective_from',
    'effective_until',
    'status',
])]
class WorkPattern extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'weekdays' => 'array',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'status' => ShiftStatus::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(WorkArrangement::class, 'work_arrangement_id');
    }
}
