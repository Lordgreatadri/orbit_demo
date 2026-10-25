<?php

use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/how-it-works', [PublicPageController::class, 'about'])->name('about');
Route::get('/user-guide', [PublicPageController::class, 'guide'])->name('guide');
Route::get('/terms', [PublicPageController::class, 'terms'])->name('terms');
Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('privacy');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

require __DIR__.'/auth.php';
require __DIR__.'/app.php';
