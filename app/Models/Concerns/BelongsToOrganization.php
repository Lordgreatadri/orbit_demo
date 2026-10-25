<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-owned model behaviour (ADR-0002):
 *  - every query is constrained to the current tenant when a tenant context exists;
 *  - organization_id is filled from the tenant context on create.
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            if ($model->organization_id === null) {
                $tenantId = app(TenantContext::class)->id();

                if ($tenantId !== null) {
                    $model->organization_id = $tenantId;
                }
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Explicit escape hatch for platform-level, audited operations only.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(OrganizationScope::class);
    }

    public function belongsToOrganization(Organization|int $organization): bool
    {
        $id = $organization instanceof Organization ? $organization->getKey() : $organization;

        return $this->organization_id !== null && (int) $this->organization_id === (int) $id;
    }
}
