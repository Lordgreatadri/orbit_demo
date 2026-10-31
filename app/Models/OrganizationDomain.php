<?php

namespace App\Models;

use App\Enums\DomainType;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\OrganizationDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'domain',
    'type',
    'is_primary',
    'is_active',
    'is_verified',
    'verification_token',
    'verified_at',
    'created_by',
    'updated_by',
])]
class OrganizationDomain extends Model
{
    /** @use HasFactory<OrganizationDomainFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DomainType::class,
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function isResolvable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->type === DomainType::CustomDomain) {
            return $this->is_verified;
        }

        return $this->is_verified;
    }
}
