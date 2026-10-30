<?php

use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\ModuleController;
use App\Http\Controllers\Platform\OrganizationController;
use App\Http\Controllers\Platform\PermissionController;
use App\Http\Controllers\Platform\RoleController;
use App\Http\Controllers\Platform\UserAccessController;
use App\Http\Controllers\Platform\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform console  (/admin, name prefix admin.)
|--------------------------------------------------------------------------
| Restricted to platform accounts holding `platform.access`. Every cross-tenant
| read or write happens here, behind explicit platform.* permissions, and is
| written to the audit log. Nothing in this file is reachable with a tenant role.
*/

Route::prefix('admin')
    ->middleware(['auth', 'account.operational', 'identity.verified', 'platform'])
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Organisations (tenants)
        Route::get('/organisations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organisations/{organization:uuid}', [OrganizationController::class, 'show'])->name('organizations.show');
        Route::patch('/organisations/{organization:uuid}/status', [OrganizationController::class, 'updateStatus'])
            ->middleware('throttle:access-write')
            ->name('organizations.status');

        // Users (system-wide) and platform account provisioning
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('throttle:access-write')->name('users.store');
        Route::get('/users/{user:uuid}', [UserController::class, 'show'])->name('users.show');
        Route::middleware('throttle:access-write')->group(function (): void {
            Route::put('/users/{user:uuid}/roles', [UserAccessController::class, 'roles'])->name('users.roles');
            Route::put('/users/{user:uuid}/permissions', [UserAccessController::class, 'permissions'])->name('users.permissions');
            Route::patch('/users/{user:uuid}/status', [UserAccessController::class, 'status'])->name('users.status');
        });

        // Roles and permissions (CRUD)
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('/roles/{role:uuid}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::middleware('throttle:access-write')->group(function (): void {
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('/roles/{role:uuid}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role:uuid}', [RoleController::class, 'destroy'])->name('roles.destroy');
            Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
            Route::put('/permissions/{permission:uuid}', [PermissionController::class, 'update'])->name('permissions.update');
            Route::delete('/permissions/{permission:uuid}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
        });

        // Audit trail and sign-in history across organisations
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/sign-ins', [AuditController::class, 'logins'])->name('audit.logins');

        // Later stages
        Route::get('/settings', [ModuleController::class, 'show'])->defaults('module', 'platform-settings')->name('settings.index');
    });
