<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * A short trail for the signed-in workspace. The current page is last and
 * is not a link. Unknown routes still name the product home.
 */
final class Breadcrumbs
{
    /**
     * @return list<array{label: string, url: ?string}>
     */
    public static function trail(?Request $request = null): array
    {
        $request ??= request();
        $name = (string) $request->route()?->getName();
        $platform = $request->user()?->isPlatformUser() ?? false;
        $home = $platform ? route('admin.dashboard') : route('org.dashboard');
        $root = ['label' => $platform ? 'Platform' : 'Workspace', 'url' => $home];

        $current = self::current($name);
        if ($current === null) {
            return [$root];
        }

        if ($name === 'org.dashboard' || $name === 'admin.dashboard') {
            return [
                ['label' => $current, 'url' => null],
            ];
        }

        return [$root, ['label' => $current, 'url' => null]];
    }

    private static function current(string $name): ?string
    {
        return match (true) {
            $name === 'org.dashboard', $name === 'admin.dashboard' => 'Dashboard',
            str_starts_with($name, 'org.analytics') => 'Analytics',
            str_starts_with($name, 'org.attendance.mine') => 'My attendance',
            str_starts_with($name, 'org.attendance.sessions') => 'QR sessions',
            str_starts_with($name, 'org.attendance') => 'Attendance',
            str_starts_with($name, 'org.field') => 'Field monitoring',
            str_starts_with($name, 'org.schedules') => 'Schedules',
            str_starts_with($name, 'org.leave') => 'Leave',
            str_starts_with($name, 'org.notifications') => 'Notifications',
            str_starts_with($name, 'org.staff') => 'Staff register',
            str_starts_with($name, 'org.departments') => 'Departments',
            str_starts_with($name, 'org.locations') => 'Locations',
            str_starts_with($name, 'org.users') => 'User accounts',
            str_starts_with($name, 'org.audit.logins') => 'Sign-in history',
            str_starts_with($name, 'org.audit') => 'Audit trail',
            str_starts_with($name, 'org.organizations') => 'Organisation settings',
            str_starts_with($name, 'org.roles') => 'Roles',
            str_starts_with($name, 'admin.organizations') => 'Organisations',
            str_starts_with($name, 'admin.users') => 'User accounts',
            str_starts_with($name, 'admin.roles') => 'Roles',
            str_starts_with($name, 'admin.permissions') => 'Permissions',
            str_starts_with($name, 'admin.audit') => 'Audit trail',
            str_starts_with($name, 'admin.sms') => 'SMS',
            str_starts_with($name, 'admin.workforce') => 'Workforce overview',
            str_starts_with($name, 'app.profile') => 'My account',
            default => null,
        };
    }
}
