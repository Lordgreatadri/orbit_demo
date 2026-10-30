<?php

namespace App\Models;

use App\Enums\LoginEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only sign-in history. Not tenant-scoped by a global scope on purpose:
 * platform operators query across organisations, and tenant screens filter by
 * organization_id explicitly (see LoginHistoryQuery).
 */
#[Fillable([
    'user_id',
    'organization_id',
    'email',
    'event',
    'guard',
    'ip_address',
    'user_agent',
    'remember',
])]
class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => LoginEventType::class,
            'remember' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOfType(Builder $query, ?string $event): Builder
    {
        return $event ? $query->where('event', $event) : $query;
    }
}
