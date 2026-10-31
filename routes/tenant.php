<?php

use App\Http\Controllers\Tenancy\BrandingController;
use App\Http\Controllers\Tenancy\DomainController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DepartmentController;
use App\Http\Controllers\Tenant\InvitationController;
use App\Http\Controllers\Tenant\LocationController;
use App\Http\Controllers\Tenant\ModuleController;
use App\Http\Controllers\Tenant\OrganizationSettingsController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\StaffMemberController;
use App\Http\Controllers\Tenant\UserAccessController;
use App\Http\Controllers\Tenant\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organisation workspace  (/org, name prefix org.)
|--------------------------------------------------------------------------
| Every route requires a tenant hostname (`tenant.host`) and an organisation
| account (`tenant`). IdentifyTenant sets context before bindings resolve.
*/

Route::prefix('org')
    ->middleware(['auth', 'tenant.host', 'account.operational', 'identity.verified', 'tenant'])
    ->name('org.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/organisation/{organization:uuid}/settings', [OrganizationSettingsController::class, 'edit'])->name('organizations.edit');
        Route::put('/organisation/{organization:uuid}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.update');
        Route::get('/organisation/{organization:uuid}/domains', [DomainController::class, 'edit'])->name('organizations.domains');
        Route::post('/organisation/{organization:uuid}/domains', [DomainController::class, 'store'])->middleware('throttle:access-write')->name('organizations.domains.store');
        Route::post('/organisation/{organization:uuid}/domains/{domain:uuid}/primary', [DomainController::class, 'primary'])->middleware('throttle:access-write')->name('organizations.domains.primary');
        Route::post('/organisation/{organization:uuid}/domains/{domain:uuid}/verify', [DomainController::class, 'verify'])->middleware('throttle:access-write')->name('organizations.domains.verify');
        Route::post('/organisation/{organization:uuid}/domains/{domain:uuid}/activate', [DomainController::class, 'activate'])->middleware('throttle:access-write')->name('organizations.domains.activate');
        Route::post('/organisation/{organization:uuid}/domains/{domain:uuid}/deactivate', [DomainController::class, 'deactivate'])->middleware('throttle:access-write')->name('organizations.domains.deactivate');
        Route::delete('/organisation/{organization:uuid}/domains/{domain:uuid}', [DomainController::class, 'destroy'])->middleware('throttle:access-write')->name('organizations.domains.destroy');
        Route::get('/organisation/{organization:uuid}/branding', [BrandingController::class, 'edit'])->name('organizations.branding');
        Route::put('/organisation/{organization:uuid}/branding', [BrandingController::class, 'update'])->middleware('throttle:access-write')->name('organizations.branding.update');
        Route::post('/organisation/{organization:uuid}/branding/publish', [BrandingController::class, 'publish'])->middleware('throttle:access-write')->name('organizations.branding.publish');
        Route::get('/organisation/{organization:uuid}/branding/preview', [BrandingController::class, 'preview'])->name('organizations.branding.preview');

        Route::get('/staff', [StaffMemberController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffMemberController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffMemberController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff:uuid}', [StaffMemberController::class, 'show'])->name('staff.show');
        Route::get('/staff/{staff:uuid}/edit', [StaffMemberController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff:uuid}', [StaffMemberController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{staff:uuid}', [StaffMemberController::class, 'destroy'])->name('staff.destroy');

        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('/departments/{department:uuid}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department:uuid}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::put('/locations/{location:uuid}', [LocationController::class, 'update'])->name('locations.update');
        Route::delete('/locations/{location:uuid}', [LocationController::class, 'destroy'])->name('locations.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{orgUser}', [UserController::class, 'show'])->name('users.show');
        Route::middleware('throttle:access-write')->group(function (): void {
            Route::put('/users/{orgUser}/roles', [UserAccessController::class, 'roles'])->name('users.roles');
            Route::put('/users/{orgUser}/permissions', [UserAccessController::class, 'permissions'])->name('users.permissions');
            Route::patch('/users/{orgUser}/status', [UserAccessController::class, 'status'])->name('users.status');
        });

        Route::post('/invitations', [InvitationController::class, 'store'])
            ->middleware('throttle:invitation-send')
            ->name('invitations.store');
        Route::delete('/invitations/{invitation:uuid}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/sign-ins', [AuditController::class, 'logins'])->name('audit.logins');

        Route::get('/attendance', [ModuleController::class, 'show'])->defaults('module', 'attendance')->name('attendance.index');
        Route::get('/attendance/sessions', [ModuleController::class, 'show'])->defaults('module', 'attendance-sessions')->name('attendance.sessions');
        Route::get('/field', [ModuleController::class, 'show'])->defaults('module', 'field')->name('field.index');
        Route::get('/schedules', [ModuleController::class, 'show'])->defaults('module', 'schedules')->name('schedules.index');
        Route::get('/leave', [ModuleController::class, 'show'])->defaults('module', 'leave')->name('leave.index');
        Route::get('/notifications', [ModuleController::class, 'show'])->defaults('module', 'notifications')->name('notifications.index');
        Route::get('/analytics', [ModuleController::class, 'show'])->defaults('module', 'analytics')->name('analytics.index');
    });
