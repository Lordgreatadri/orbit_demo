<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffMember;
use App\Models\User;

/**
 * Builds the sidebar for the signed-in account. Items are filtered by
 * policies/permissions here so the view stays declarative; the server still
 * authorises every request independently (hiding a link is not authorisation).
 *
 * Item shape:
 *   label, route, params?, icon?, active? (route pattern), soon? (bool),
 *   children? (list of items without icons)
 *
 * @phpstan-type Item array{label: string, route?: string, params?: mixed, icon?: string, active?: string, soon?: bool, children?: list<array<string, mixed>>}
 */
class Navigation
{
    /**
     * @return list<array{heading: string, items: list<Item>}>
     */
    public static function for(User $user): array
    {
        $sections = $user->isPlatformUser() ? self::platform($user) : self::tenant($user);

        return array_values(array_filter(array_map(function (array $section): array {
            $section['items'] = array_values(array_filter(array_map(function (array $item): ?array {
                if (isset($item['children'])) {
                    $item['children'] = array_values(array_filter($item['children'], fn (array $child) => $child['can'] ?? true));
                    unset($item['can']);

                    return $item['children'] === [] ? null : $item;
                }

                return ($item['can'] ?? true) ? $item : null;
            }, $section['items'])));

            return $section;
        }, $sections), fn (array $section) => $section['items'] !== []));
    }

    /**
     * @return list<array{heading: string, items: list<Item>}>
     */
    private static function tenant(User $user): array
    {
        $organization = $user->organization;

        return [
            ['heading' => 'Overview', 'items' => [
                ['label' => 'Dashboard', 'route' => 'org.dashboard', 'icon' => 'home', 'active' => 'org.dashboard'],
            ]],
            ['heading' => 'People', 'items' => [
                ['label' => 'Staff register', 'route' => 'org.staff.index', 'icon' => 'id-card', 'active' => 'org.staff.*', 'can' => $user->can('viewAny', StaffMember::class)],
                ['label' => 'Departments', 'route' => 'org.departments.index', 'icon' => 'building', 'active' => 'org.departments.*', 'can' => $user->can('viewAny', Department::class)],
                ['label' => 'Users & access', 'icon' => 'users', 'active' => 'org.users.*|org.roles.*', 'children' => [
                    ['label' => 'User accounts', 'route' => 'org.users.index', 'active' => 'org.users.*', 'can' => $user->can('viewAny', User::class)],
                    ['label' => 'Roles & permissions', 'route' => 'org.roles.index', 'active' => 'org.roles.*', 'can' => $user->can('viewAny', Role::class)],
                ]],
            ]],
            ['heading' => 'Attendance', 'items' => [
                ['label' => 'Records', 'route' => 'org.attendance.index', 'icon' => 'clock', 'active' => 'org.attendance.index', 'soon' => true, 'can' => $user->can('attendance.view')],
                ['label' => 'QR sessions', 'route' => 'org.attendance.sessions', 'icon' => 'qr', 'active' => 'org.attendance.sessions', 'soon' => true, 'can' => $user->can('attendance.manage')],
                ['label' => 'Field monitoring', 'route' => 'org.field.index', 'icon' => 'map-pin', 'active' => 'org.field.*', 'soon' => true, 'can' => $user->can('attendance.view')],
                ['label' => 'Locations', 'route' => 'org.locations.index', 'icon' => 'globe', 'active' => 'org.locations.*', 'can' => $user->can('viewAny', Location::class)],
            ]],
            ['heading' => 'Workforce', 'items' => [
                ['label' => 'Schedules', 'route' => 'org.schedules.index', 'icon' => 'calendar', 'active' => 'org.schedules.*', 'soon' => true, 'can' => $user->can('schedules.view')],
                ['label' => 'Leave', 'route' => 'org.leave.index', 'icon' => 'document-check', 'active' => 'org.leave.*', 'soon' => true, 'can' => $user->can('leave.request') || $user->can('leave.approve')],
            ]],
            ['heading' => 'Insights', 'items' => [
                ['label' => 'Analytics', 'route' => 'org.analytics.index', 'icon' => 'chart', 'active' => 'org.analytics.*', 'soon' => true, 'can' => $user->can('analytics.view')],
                ['label' => 'Notifications', 'route' => 'org.notifications.index', 'icon' => 'bell', 'active' => 'org.notifications.*', 'soon' => true, 'can' => $user->can('notifications.manage')],
            ]],
            ['heading' => 'Administration', 'items' => [
                ['label' => 'Organisation settings', 'route' => 'org.organizations.edit', 'params' => $organization, 'icon' => 'settings', 'active' => 'org.organizations.*', 'can' => $organization instanceof Organization && $user->can('view', $organization)],
                ['label' => 'Audit & security', 'icon' => 'shield-check', 'active' => 'org.audit.*', 'children' => [
                    ['label' => 'Audit trail', 'route' => 'org.audit.index', 'active' => 'org.audit.index', 'can' => $user->can('audit.view')],
                    ['label' => 'Sign-in history', 'route' => 'org.audit.logins', 'active' => 'org.audit.logins', 'can' => $user->can('audit.logins.view')],
                ]],
            ]],
        ];
    }

    /**
     * @return list<array{heading: string, items: list<Item>}>
     */
    private static function platform(User $user): array
    {
        return [
            ['heading' => 'Overview', 'items' => [
                ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'active' => 'admin.dashboard'],
            ]],
            ['heading' => 'Tenants', 'items' => [
                ['label' => 'Organisations', 'route' => 'admin.organizations.index', 'icon' => 'building', 'active' => 'admin.organizations.*', 'can' => $user->can('viewAnyAsPlatform', Organization::class)],
            ]],
            ['heading' => 'Access control', 'items' => [
                ['label' => 'Users', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => 'admin.users.*', 'can' => $user->can('viewAny', User::class)],
                ['label' => 'Roles', 'route' => 'admin.roles.index', 'icon' => 'key', 'active' => 'admin.roles.*', 'can' => $user->can('viewAny', Role::class)],
                ['label' => 'Permissions', 'route' => 'admin.permissions.index', 'icon' => 'lock', 'active' => 'admin.permissions.*', 'can' => $user->can('viewAny', Permission::class)],
            ]],
            ['heading' => 'Security', 'items' => [
                ['label' => 'Audit trail', 'route' => 'admin.audit.index', 'icon' => 'shield-check', 'active' => 'admin.audit.index', 'can' => $user->can('platform.audit.view')],
                ['label' => 'Sign-in history', 'route' => 'admin.audit.logins', 'icon' => 'login', 'active' => 'admin.audit.logins', 'can' => $user->can('platform.audit.view')],
            ]],
            ['heading' => 'Product modules', 'items' => [
                ['label' => 'Attendance records', 'route' => 'admin.modules.show', 'params' => ['module' => 'attendance'], 'icon' => 'clock', 'active' => 'admin.modules.show', 'soon' => true, 'can' => $user->can('platform.access')],
                ['label' => 'QR sessions', 'route' => 'admin.modules.show', 'params' => ['module' => 'attendance-sessions'], 'icon' => 'qr', 'soon' => true, 'can' => $user->can('platform.access')],
                ['label' => 'Field monitoring', 'route' => 'admin.modules.show', 'params' => ['module' => 'field'], 'icon' => 'map-pin', 'soon' => true, 'can' => $user->can('platform.access')],
                ['label' => 'Schedules', 'route' => 'admin.modules.show', 'params' => ['module' => 'schedules'], 'icon' => 'calendar', 'soon' => true, 'can' => $user->can('platform.access')],
                ['label' => 'Leave', 'route' => 'admin.modules.show', 'params' => ['module' => 'leave'], 'icon' => 'document-check', 'soon' => true, 'can' => $user->can('platform.access')],
                ['label' => 'Notifications', 'route' => 'admin.modules.show', 'params' => ['module' => 'notifications'], 'icon' => 'bell', 'soon' => true, 'can' => $user->can('platform.access')],
                ['label' => 'Analytics', 'route' => 'admin.modules.show', 'params' => ['module' => 'analytics'], 'icon' => 'chart', 'soon' => true, 'can' => $user->can('platform.access')],
            ]],
            ['heading' => 'Platform', 'items' => [
                ['label' => 'Settings', 'route' => 'admin.settings.index', 'icon' => 'settings', 'active' => 'admin.settings.*', 'soon' => true, 'can' => $user->can('platform.settings.manage')],
            ]],
        ];
    }

    /**
     * Whether the current request matches an item's active pattern(s).
     */
    public static function isActive(array $item): bool
    {
        $patterns = isset($item['active']) ? explode('|', $item['active']) : (isset($item['route']) ? [$item['route']] : []);

        foreach ($patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                if (isset($item['params']['module']) && request()->route('module') !== $item['params']['module']) {
                    continue;
                }

                return true;
            }
        }

        foreach ($item['children'] ?? [] as $child) {
            if (self::isActive($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flat list of visible labels (parents and children) for tests and search.
     *
     * @return list<string>
     */
    public static function labels(User $user): array
    {
        $labels = [];

        foreach (self::for($user) as $section) {
            foreach ($section['items'] as $item) {
                $labels[] = $item['label'];

                foreach ($item['children'] ?? [] as $child) {
                    $labels[] = $child['label'];
                }
            }
        }

        return $labels;
    }
}
