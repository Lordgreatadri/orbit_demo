<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'department_id',
    'location_id',
    'work_pattern_id',
    'work_arrangement_id',
    'created_by',
    'name',
    'starts_at',
    'ends_at',
    'crosses_midnight',
    'effective_from',
    'effective_until',
    'status',
])]
class Shift extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'status' => ShiftStatus::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(WorkArrangement::class, 'work_arrangement_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }
}
