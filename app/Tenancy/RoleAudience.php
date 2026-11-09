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
     * Wording for a role in this organisation. School terms such as lecturer
     * are used only for educational institutions.
     */
    public static function description(Role $role, ?Organization $organization): string
    {
        $type = $organization?->type?->value;
        $override = is_string($type)
            ? config('orbit.role_description_overrides.'.$type.'.'.$role->name)
            : null;

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $configured = config('orbit.role_descriptions.'.$role->name);

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (string) ($role->description ?? '');
    }

    /**
     * @return array<string, string>
     */
    public static function invitableOptions(Organization $organization): array
    {
        return self::tenantRolesFor($organization)
            ->mapWithKeys(fn (Role $role) => [$role->name => $role->label()])
            ->all();
    }
}
