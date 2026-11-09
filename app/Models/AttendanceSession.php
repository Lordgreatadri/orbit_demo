<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'location_id',
    'created_by',
    'name',
    'starts_at',
    'ends_at',
    'status',
    'qr_ttl_seconds',
    'require_location',
])]
class AttendanceSession extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => SessionStatus::class,
            'qr_ttl_seconds' => 'integer',
            'require_location' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(AttendanceToken::class);
    }

    public function isOpen(): bool
    {
        return $this->status === SessionStatus::Active
            && $this->starts_at->lte(now())
            && $this->ends_at->gte(now());
    }
}
