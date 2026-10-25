<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin wrapper around spatie/laravel-activitylog that always records the tenant
 * and never accepts secrets in properties (callers must pass safe metadata only).
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        ?User $causer = null,
        ?int $organizationId = null,
        string $logName = 'orbit',
    ): Activity {
        $organizationId ??= $causer?->organization_id
            ?? ($subject?->getAttribute('organization_id'))
            ?? app(TenantContext::class)->id();

        $logger = activity($logName)->event($event)->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if ($causer !== null) {
            $logger->causedBy($causer);
        }

        $logger->tap(function (Activity $activity) use ($organizationId): void {
            $activity->organization_id = $organizationId;
        });

        /** @var Activity */
        return $logger->log($event);
    }
}
