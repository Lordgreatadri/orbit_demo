<?php

use App\Http\Controllers\Branding\BrandingAssetController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages (central host) and tenant branding assets
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicPageController::class, 'home'])->name('home');

Route::middleware('central.host')->group(function (): void {
    Route::get('/how-it-works', [PublicPageController::class, 'about'])->name('about');
    Route::get('/user-guide', [PublicPageController::class, 'guide'])->name('guide');
    Route::get('/terms', [PublicPageController::class, 'terms'])->name('terms');
    Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('privacy');

    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:contact')
        ->name('contact.store');
});

Route::get('/branding/{slot}', BrandingAssetController::class)
    ->whereIn('slot', ['logo', 'logo-light', 'logo-dark', 'favicon', 'login-image'])
    ->name('branding.asset');

// auth.php, app.php, tenant.php and superadmin.php are registered in bootstrap/app.php.
