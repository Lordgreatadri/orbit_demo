<?php

namespace App\Access;

use App\Enums\RoleScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\RoleAudience;
use Illuminate\Support\Collection;

/**
 * Decides which roles and permissions one account may grant to another.
 *
 * Rules (ADR-0009 §5):
 *  - Platform roles/permissions only ever go to platform accounts; tenant
 *    roles/permissions only to organisation accounts. Role::scope and
 *    Permission::module encode this, and User::assignRole re-checks it.
 *  - Only a platform super administrator may grant the `platform_super_admin`
 *    role. `platform.users.manage` on its own is not enough to mint one.
 *  - An organisation administrator may grant any tenant-scoped role (including
 *    custom roles defined in the platform console) and only permissions they
 *    hold themselves (no privilege escalation through direct permissions).
 */
class GrantResolver
{
    /**
     * @return Collection<int, Role>
     */
    public function grantableRoles(User $actor, User $target): Collection
    {
        if ($actor->isPlatformUser()) {
            if (! $actor->can('platform.users.manage')) {
                return collect();
            }

            $scope = $target->isPlatformUser() ? RoleScope::Platform : RoleScope::Tenant;
            $roles = Role::query()->where('scope', $scope->value)->orderBy('name')->get();

            if ($scope === RoleScope::Tenant && $target->organization) {
                $roles = $roles->filter(fn (Role $role) => RoleAudience::allows($role->name, $target->organization->type))->values();
            }

            return $this->withoutSuperAdminUnlessActorIsOne($actor, $roles);
        }

        if ($target->isPlatformUser() || ! $actor->belongsToOrganization((int) $target->organization_id) || ! $actor->can('users.manage')) {
            return collect();
        }

        $target->loadMissing('organization');

        if ($target->organization === null) {
            return collect();
        }

        return RoleAudience::tenantRolesFor($target->organization);
    }

    /**
     * Platform roles the actor may pick when provisioning a new platform account.
     *
     * @return Collection<int, Role>
     */
    public function grantablePlatformRoles(User $actor): Collection
    {
        if (! $actor->isPlatformUser() || ! $actor->can('platform.users.manage')) {
            return collect();
        }

        return $this->withoutSuperAdminUnlessActorIsOne(
            $actor,
            Role::query()->platform()->orderBy('name')->get(),
        );
    }

    /**
     * @return Collection<int, Permission>
     */
    public function grantablePermissions(User $actor, User $target): Collection
    {
        if ($actor->isPlatformUser()) {
            if (! $actor->can('platform.users.manage')) {
                return collect();
            }

            $query = $target->isPlatformUser()
                ? Permission::query()->platformOnly()
                : Permission::query()->tenantGrantable();

            return $query->orderBy('module')->orderBy('name')->get();
        }

        if ($target->isPlatformUser() || ! $actor->belongsToOrganization((int) $target->organization_id) || ! $actor->can('users.manage')) {
            return collect();
        }

        $held = $actor->getAllPermissions()->pluck('name')->all();

        return Permission::query()
            ->tenantGrantable()
            ->whereIn('name', $held)
            ->orderBy('module')
            ->orderBy('name')
            ->get();
    }

    /**
     * Is `$actor` allowed to change `$target`'s access at all?
     */
    public function canManage(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->isPlatformUser()) {
            return $actor->can('platform.users.manage');
        }

        return ! $target->isPlatformUser()
            && $actor->belongsToOrganization((int) $target->organization_id)
            && $actor->can('users.manage');
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return Collection<int, Role>
     */
    private function withoutSuperAdminUnlessActorIsOne(User $actor, Collection $roles): Collection
    {
        if ($actor->isPlatformSuperAdmin()) {
            return $roles;
        }

        return $roles->reject(fn (Role $role) => $role->name === 'platform_super_admin')->values();
    }
}
