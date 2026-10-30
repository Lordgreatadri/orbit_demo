<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product identity
    |--------------------------------------------------------------------------
    */

    'product_name' => env('APP_NAME', 'ORBIT'),
    'product_tagline' => 'Online Real-Time Based Identification and Tracking System',
    'support_email' => env('ORBIT_SUPPORT_EMAIL', 'hello@orbit.example'),

    /*
    |--------------------------------------------------------------------------
    | Contact form
    |--------------------------------------------------------------------------
    | Public contact submissions are stored and (optionally) forwarded to this
    | address. Leave null to store only.
    */

    'contact_recipient' => env('ORBIT_CONTACT_RECIPIENT'),

    /*
    |--------------------------------------------------------------------------
    | Verification
    |--------------------------------------------------------------------------
    | When true, authenticated application routes require a verified e-mail
    | address. Disable only in environments where mail cannot be delivered.
    */

    'require_email_verification' => (bool) env('ORBIT_REQUIRE_EMAIL_VERIFICATION', true),

    /*
    | When true, workspace routes stay closed until phone_verified_at is set.
    | The one-time code is delivered through the SMS binding. Until a provider
    | is configured that binding writes the code to the application log.
    */

    'require_phone_verification' => (bool) env('ORBIT_REQUIRE_PHONE_VERIFICATION', true),

    /*
    |--------------------------------------------------------------------------
    | Legal
    |--------------------------------------------------------------------------
    | Version label recorded against a user when they accept the Terms and
    | Privacy Policy during registration. Bump when the documents change.
    */

    'terms_version' => env('ORBIT_TERMS_VERSION', '2026-10-draft'),

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */

    'invitation_ttl_days' => (int) env('ORBIT_INVITATION_TTL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Roles and permissions
    |--------------------------------------------------------------------------
    | Platform roles may only be held by accounts without an organisation.
    | Tenant roles may only be held by accounts that belong to an organisation.
    */

    'roles' => [
        'platform' => [
            'platform_super_admin' => 'Platform super administrator',
            'platform_support' => 'Platform support',
        ],
        'tenant' => [
            'organization_admin' => 'Organisation administrator',
            'organization_manager' => 'Organisation manager',
            'member' => 'Member',
            'student' => 'Student',
        ],
    ],

    'role_descriptions' => [
        'platform_super_admin' => 'Full control of the platform console: organisations, users, roles, permissions, audit, and settings.',
        'platform_support' => 'Read-only platform console access for support staff. Cannot change roles, permissions, or organisations.',
        'organization_admin' => 'Manages the organisation: settings, staff register, user accounts, roles, and audit trail.',
        'organization_manager' => 'Operational supervisor: staff, departments, attendance, schedules, and leave decisions.',
        'member' => 'Employee, staff member, lecturer, or field officer. Records own attendance and requests leave.',
        'student' => 'Learner account. Records own attendance and views own schedule.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission catalogue
    |--------------------------------------------------------------------------
    | The dotted prefix is the module. `platform.*` permissions may only be held
    | by platform roles; every other module is grantable inside an organisation.
    | Each entry lists the system roles that receive it by default. The seeder is
    | idempotent and only touches `is_system` rows, so roles and permissions
    | created by a super administrator in the console are preserved.
    */

    'permission_modules' => [
        'platform' => 'Platform console',
        'organization' => 'Organisation',
        'users' => 'Users & access',
        'staff' => 'Staff register',
        'departments' => 'Departments',
        'locations' => 'Locations',
        'attendance' => 'Attendance',
        'schedules' => 'Schedules',
        'leave' => 'Leave',
        'analytics' => 'Analytics',
        'notifications' => 'Notifications',
        'audit' => 'Audit & security',
    ],

    'permissions' => [
        // Platform console (never grantable to organisation accounts)
        'platform.access' => ['Open the platform console', ['platform_super_admin', 'platform_support']],
        'platform.organizations.view' => ['View every organisation', ['platform_super_admin', 'platform_support']],
        'platform.organizations.manage' => ['Suspend, reactivate, or close organisations', ['platform_super_admin']],
        'platform.users.view' => ['View every user account across organisations', ['platform_super_admin', 'platform_support']],
        'platform.users.manage' => ['Create platform accounts and change any user\'s roles, permissions, or status', ['platform_super_admin']],
        'platform.roles.manage' => ['Create, edit, and delete roles and permissions', ['platform_super_admin']],
        'platform.audit.view' => ['View the platform-wide audit trail and sign-in history', ['platform_super_admin', 'platform_support']],
        'platform.settings.manage' => ['Change platform settings and providers', ['platform_super_admin']],

        // Organisation
        'organization.view' => ['View organisation settings', ['organization_admin', 'organization_manager']],
        'organization.update' => ['Change organisation settings', ['organization_admin']],

        // Users & access (login accounts)
        'users.view' => ['View user accounts in the organisation', ['organization_admin', 'organization_manager']],
        'users.invite' => ['Invite new users and revoke invitations', ['organization_admin']],
        'users.manage' => ['Assign or revoke roles and permissions, suspend or reactivate users', ['organization_admin']],

        // Staff register (people records, independent of login accounts)
        'staff.view' => ['View the staff register', ['organization_admin', 'organization_manager']],
        'staff.manage' => ['Add, edit, link, and archive staff records', ['organization_admin', 'organization_manager']],

        // Structure
        'departments.manage' => ['Create and edit departments', ['organization_admin', 'organization_manager']],
        'locations.view' => ['View approved locations', ['organization_admin', 'organization_manager']],
        'locations.manage' => ['Create and edit approved locations and geofences', ['organization_admin']],

        // Attendance (module delivered in a later stage; permissions reserved now)
        'attendance.view' => ['View attendance records', ['organization_admin', 'organization_manager']],
        'attendance.manage' => ['Open attendance sessions and correct records', ['organization_admin', 'organization_manager']],
        'attendance.record' => ['Record own attendance', ['organization_admin', 'organization_manager', 'member', 'student']],

        // Schedules & leave
        'schedules.view' => ['View schedules', ['organization_admin', 'organization_manager', 'member', 'student']],
        'schedules.manage' => ['Create shifts and assignments', ['organization_admin', 'organization_manager']],
        'leave.request' => ['Submit own leave requests', ['organization_admin', 'organization_manager', 'member', 'student']],
        'leave.approve' => ['Approve or reject leave requests', ['organization_admin', 'organization_manager']],

        // Insights & communication
        'analytics.view' => ['View workforce analytics', ['organization_admin', 'organization_manager']],
        'notifications.manage' => ['Configure notifications for the organisation', ['organization_admin', 'organization_manager']],

        // Audit & security
        'audit.view' => ['View the organisation audit trail', ['organization_admin']],
        'audit.logins.view' => ['View the organisation sign-in history', ['organization_admin']],
    ],

    /*
    | Default tenant roles offered when inviting a new account. Organisation
    | administrators may also grant any other tenant-scoped role (including
    | custom roles created in the platform console). Platform roles are never
    | grantable from an organisation.
    */
    'invitable_roles' => ['organization_admin', 'organization_manager', 'member', 'student'],

    /*
    |--------------------------------------------------------------------------
    | Modules and delivery status
    |--------------------------------------------------------------------------
    | Drives the sidebar and the "coming soon" pages. `available` modules link
    | to real screens; the others render a labelled placeholder so the product
    | never shows dead links (requirements 05 §Admin navigation).
    */

    'modules' => [
        'attendance' => [
            'label' => 'Attendance records',
            'description' => 'Daily attendance by member, location, department, and status, with check-in and check-out times.',
            'stage' => 'Stage 4 · step 3',
            'permission' => 'attendance.view',
            'available' => false,
        ],
        'attendance-sessions' => [
            'label' => 'QR sessions',
            'description' => 'Time-limited office attendance sessions with a rotating QR code and optional location verification.',
            'stage' => 'Stage 4 · step 3',
            'permission' => 'attendance.manage',
            'available' => false,
        ],
        'field' => [
            'label' => 'Field monitoring',
            'description' => 'Field attendance with explicit GPS permission, server-side geofence checks, and an activity view.',
            'stage' => 'Stage 4 · step 4',
            'permission' => 'attendance.view',
            'available' => false,
        ],
        'schedules' => [
            'label' => 'Schedules',
            'description' => 'Shifts by department or location and member assignments.',
            'stage' => 'Stage 4 · step 5',
            'permission' => 'schedules.view',
            'available' => false,
        ],
        'leave' => [
            'label' => 'Leave',
            'description' => 'Leave requests with type, dates, and reason; authorised approvers decide and the decision is recorded.',
            'stage' => 'Stage 4 · step 6',
            'permission' => 'leave.request',
            'available' => false,
        ],
        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Attendance confirmations, shift reminders, and leave outcomes by e-mail, with SMS once a provider is configured.',
            'stage' => 'Stage 4 · step 7',
            'permission' => 'notifications.manage',
            'available' => false,
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Attendance rate, office and field attendance, and late attendance with a defined denominator and date range.',
            'stage' => 'Stage 4 · step 8',
            'permission' => 'analytics.view',
            'available' => false,
        ],
        'platform-settings' => [
            'label' => 'Platform settings',
            'description' => 'SMS and e-mail providers, verification toggles, and other platform-wide configuration.',
            'stage' => 'Stage 5',
            'permission' => 'platform.settings.manage',
            'available' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local demo seed
    |--------------------------------------------------------------------------
    | Used only by SystemDemoUserSeeder. Never set a value in production.
    | The seeder refuses to run when APP_ENV=production.
    */

    'demo_password' => env('ORBIT_DEMO_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Organisation reference data
    |--------------------------------------------------------------------------
    */

    'default_timezone' => 'Africa/Accra',
    'default_country' => 'GH',

];
