<?php

use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\InvitationController;
use App\Http\Controllers\App\MemberController;
use App\Http\Controllers\App\OrganizationSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
| Externally addressable entities are bound by UUID. Tenant context is set by
| the global SetTenantContext middleware; policies re-check organisation
| membership on every action.
*/

$middleware = ['auth', 'account.operational', 'identity.verified'];

Route::prefix('app')->middleware($middleware)->name('app.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/organisations/{organization:uuid}/settings', [OrganizationSettingsController::class, 'edit'])
        ->name('organizations.edit');
    Route::put('/organisations/{organization:uuid}/settings', [OrganizationSettingsController::class, 'update'])
        ->name('organizations.update');

    Route::get('/members', [MemberController::class, 'index'])->name('members.index');

    Route::post('/invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:invitation-send')
        ->name('invitations.store');
    Route::delete('/invitations/{invitation:uuid}', [InvitationController::class, 'destroy'])
        ->name('invitations.destroy');
});
