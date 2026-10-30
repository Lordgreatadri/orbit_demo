<?php

use App\Http\Controllers\App\ProfileController;
use App\Http\Controllers\App\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Account-neutral authenticated routes
|--------------------------------------------------------------------------
| /app is the single post-login landing point. It sends organisation accounts
| to the organisation workspace (routes/tenant.php) and platform accounts to
| the platform console (routes/superadmin.php). Pages that belong to the
| signed-in person regardless of account type (profile) also live here.
*/

Route::prefix('app')
    ->middleware(['auth', 'account.operational', 'identity.verified'])
    ->name('app.')
    ->group(function (): void {
        Route::get('/', WorkspaceController::class)->name('dashboard');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    });
