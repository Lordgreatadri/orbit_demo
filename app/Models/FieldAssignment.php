<?php

namespace App\Models;

use App\Enums\FieldAssignmentStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'staff_member_id',
    'location_id',
    'assigned_by',
    'title',
    'instructions',
    'starts_on',
    'ends_on',
    'status',
])]
class FieldAssignment extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => FieldAssignmentStatus::class,
        ];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FieldActivity::class);
    }

    public function coversToday(): bool
    {
        $today = now($this->organization?->timezone ?? 'UTC')->toDateString();

        return $this->starts_on->toDateString() <= $today && $this->ends_on->toDateString() >= $today;
    }
}
