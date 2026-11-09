<?php

use App\Http\Controllers\Tenancy\BrandingController;
use App\Http\Controllers\Tenancy\DomainController;
use App\Http\Controllers\Tenant\AnalyticsController;
use App\Http\Controllers\Tenant\AttendanceController;
use App\Http\Controllers\Tenant\AttendanceSessionController;
use App\Http\Controllers\Tenant\AuditController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\DepartmentController;
use App\Http\Controllers\Tenant\FieldController;
use App\Http\Controllers\Tenant\InvitationController;
use App\Http\Controllers\Tenant\LeaveController;
use App\Http\Controllers\Tenant\LocationController;
use App\Http\Controllers\Tenant\OrganizationSettingsController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\ScheduleController;
use App\Http\Controllers\Tenant\StaffMemberController;
use App\Http\Controllers\Tenant\UserAccessController;
use App\Http\Controllers\Tenant\UserController;
use App\Http\Controllers\Tenant\WorkforceNotificationController;
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
        Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('/departments/{department:uuid}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::put('/departments/{department:uuid}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::delete('/departments/{department:uuid}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/locations/create', [LocationController::class, 'create'])->name('locations.create');
        Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/locations/{location:uuid}/edit', [LocationController::class, 'edit'])->name('locations.edit');
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
        Route::post('/invitations/{invitation:uuid}/resend', [InvitationController::class, 'resend'])
            ->middleware('throttle:invitation-send')
            ->name('invitations.resend');
        Route::delete('/invitations/{invitation:uuid}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/sign-ins', [AuditController::class, 'logins'])->name('audit.logins');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/mine', [AttendanceController::class, 'mine'])->name('attendance.mine');
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->middleware('throttle:attendance')->name('attendance.check-in');
        Route::get('/attendance/arrive/{token}', [AttendanceController::class, 'arrive'])
            ->where('token', '[A-Za-z0-9]{20,80}')
            ->middleware('throttle:attendance')
            ->name('attendance.arrive');
        Route::post('/attendance/arrive/{token}', [AttendanceController::class, 'arriveStore'])
            ->where('token', '[A-Za-z0-9]{20,80}')
            ->middleware('throttle:attendance')
            ->name('attendance.arrive.store');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->middleware('throttle:attendance')->name('attendance.check-out');
        Route::post('/attendance/corrections', [AttendanceController::class, 'correct'])->middleware('throttle:attendance')->name('attendance.corrections');

        Route::get('/attendance/sessions', [AttendanceSessionController::class, 'index'])->name('attendance.sessions');
        Route::post('/attendance/sessions', [AttendanceSessionController::class, 'store'])->middleware('throttle:attendance')->name('attendance.sessions.store');
        Route::get('/attendance/sessions/{session}', [AttendanceSessionController::class, 'show'])->name('attendance.sessions.show');
        Route::post('/attendance/sessions/{session}/token', [AttendanceSessionController::class, 'token'])->middleware('throttle:attendance')->name('attendance.sessions.token');
        Route::post('/attendance/sessions/{session}/code', [AttendanceController::class, 'code'])->middleware('throttle:attendance')->name('attendance.sessions.code');
        Route::post('/attendance/sessions/{session}/{status}', [AttendanceSessionController::class, 'status'])->whereIn('status', ['active', 'paused', 'closed'])->name('attendance.sessions.status');
        Route::post('/attendance/beacons', [AttendanceSessionController::class, 'beacon'])->name('attendance.beacons.store');
        Route::post('/attendance/wifi', [AttendanceSessionController::class, 'wifi'])->name('attendance.wifi.store');
        Route::post('/attendance/otps', [AttendanceSessionController::class, 'otp'])->middleware('throttle:attendance')->name('attendance.otps.store');

        Route::get('/field', [FieldController::class, 'index'])->name('field.index');
        Route::post('/field', [FieldController::class, 'store'])->name('field.store');
        Route::post('/field/{assignment}/start', [FieldController::class, 'start'])->middleware('throttle:attendance')->name('field.start');
        Route::post('/field/activities/{activity}/finish', [FieldController::class, 'finish'])->middleware('throttle:attendance')->name('field.finish');

        Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
        Route::post('/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
        Route::post('/schedules/{shift}/publish', [ScheduleController::class, 'publish'])->name('schedules.publish');
        Route::post('/schedules/{shift}/assignments', [ScheduleController::class, 'assign'])->name('schedules.assign');

        Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
        Route::post('/leave/types', [LeaveController::class, 'type'])->name('leave.types.store');
        Route::get('/leave/types/{leaveType}/edit', [LeaveController::class, 'editType'])->name('leave.types.edit');
        Route::put('/leave/types/{leaveType}', [LeaveController::class, 'updateType'])->name('leave.types.update');
        Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
        Route::post('/leave/{leaveRequest}/decide', [LeaveController::class, 'decide'])->name('leave.decide');
        Route::get('/leave/{leaveRequest}/document', [LeaveController::class, 'document'])->name('leave.document');

        Route::get('/notifications', [WorkforceNotificationController::class, 'index'])->name('notifications.index');
        Route::put('/notifications', [WorkforceNotificationController::class, 'update'])->name('notifications.update');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->middleware('throttle:attendance')->name('analytics.export');
    });
