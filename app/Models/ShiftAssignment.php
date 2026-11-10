<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'shift_id',
    'staff_member_id',
    'work_arrangement_id',
    'location_id',
    'work_pattern_id',
    'work_date',
    'starts_at',
    'ends_at',
    'crosses_midnight',
    'reminded_at',
])]
class ShiftAssignment extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'crosses_midnight' => 'boolean',
            'reminded_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function arrangement(): BelongsTo
    {
        return $this->belongsTo(WorkArrangement::class, 'work_arrangement_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
