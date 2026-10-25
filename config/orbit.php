<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product identity
    |--------------------------------------------------------------------------
    */

    'product_name' => 'ORBIT',
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

    'permissions' => [
        'platform.access' => ['platform_super_admin', 'platform_support'],
        'organization.view' => ['platform_super_admin', 'organization_admin', 'organization_manager'],
        'organization.update' => ['platform_super_admin', 'organization_admin'],
        'members.view' => ['platform_super_admin', 'platform_support', 'organization_admin', 'organization_manager'],
        'members.invite' => ['platform_super_admin', 'organization_admin'],
        'members.manage' => ['platform_super_admin', 'organization_admin'],
    ],

    /*
    | Roles an organisation administrator may grant through invitations.
    */
    'invitable_roles' => ['organization_admin', 'organization_manager', 'member'],

    /*
    |--------------------------------------------------------------------------
    | Organisation reference data
    |--------------------------------------------------------------------------
    */

    'default_timezone' => 'Africa/Accra',
    'default_country' => 'GH',

];
