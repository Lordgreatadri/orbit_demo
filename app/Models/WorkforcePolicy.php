<?php

namespace App\Models;

use App\Enums\VerificationMethod;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'organization_id',
    'grace_minutes',
    'work_start',
    'work_end',
    'qr_ttl_seconds',
    'max_gps_accuracy_m',
    'location_max_age_seconds',
    'remote_without_geofence',
    'require_device_binding',
    'otp_enabled',
    'field_delay_minutes',
    'enabled_methods',
])]
class WorkforcePolicy extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'qr_ttl_seconds' => 'integer',
            'max_gps_accuracy_m' => 'integer',
            'location_max_age_seconds' => 'integer',
            'remote_without_geofence' => 'boolean',
            'require_device_binding' => 'boolean',
            'otp_enabled' => 'boolean',
            'field_delay_minutes' => 'integer',
            'enabled_methods' => 'array',
        ];
    }

    public static function resolve(Organization $organization): self
    {
        $policy = static::withoutTenantScope()->firstOrCreate(
            ['organization_id' => $organization->getKey()],
            [
                'grace_minutes' => 10,
                'work_start' => '08:00:00',
                'work_end' => '17:00:00',
                'qr_ttl_seconds' => 30,
                'max_gps_accuracy_m' => 100,
                'location_max_age_seconds' => 120,
                'remote_without_geofence' => true,
                'require_device_binding' => false,
                'otp_enabled' => false,
                'field_delay_minutes' => 60,
                'enabled_methods' => VerificationMethod::operable(),
            ],
        );

        return $policy;
    }

    public function allows(VerificationMethod $method): bool
    {
        if ($method->isReserved()) {
            return false;
        }

        return in_array($method->value, $this->enabled_methods ?? [], true);
    }
}
