<?php

namespace App\Tenancy;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Support\Collection;

/**
 * Some tenant roles only apply to certain organisation types (e.g. student
 * is for educational institutions, not an EPA-style public agency).
 */
final class RoleAudience
{
    public static function allows(string $role, ?OrganizationType $type): bool
    {
        $audiences = config('orbit.role_audiences.'.$role);

        if (! is_array($audiences) || $audiences === []) {
            return true;
        }

        if ($type === null) {
            return false;
        }

        return in_array($type->value, $audiences, true);
    }

    /**
     * @return Collection<int, Role>
     */
    public static function tenantRolesFor(Organization $organization): Collection
    {
        return Role::query()
            ->tenant()
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => self::allows($role->name, $organization->type))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public static function invitableOptions(Organization $organization): array
    {
        $allowed = self::tenantRolesFor($organization)->pluck('name')->all();

        return collect(config('orbit.invitable_roles', []))
            ->filter(fn (string $name) => in_array($name, $allowed, true))
            ->mapWithKeys(fn (string $name) => [$name => config('orbit.roles.tenant.'.$name, $name)])
            ->all();
    }
}
