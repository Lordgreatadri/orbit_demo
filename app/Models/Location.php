<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'type',
    'address_line',
    'city',
    'district',
    'region',
    'latitude',
    'longitude',
    'geofence_radius_m',
    'is_active',
])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToOrganization, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_radius_m' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class, 'primary_location_id');
    }

    public function hasGeofence(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->geofence_radius_m !== null;
    }
}
