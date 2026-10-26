<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Auth\RegisteredOrganizationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Invitations\InvitationAcceptanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication and onboarding
|--------------------------------------------------------------------------
| /register creates an ORGANISATION and its responsible contact. There is no
| member/student/employee self-registration route; members join only through
| /invitations/{token} issued by an authorised organisation administrator.
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredOrganizationController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredOrganizationController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');

    Route::get('/invitations/{token}', [InvitationAcceptanceController::class, 'show'])
        ->middleware('throttle:invitation-accept')
        ->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:invitation-accept')
        ->name('invitations.accept');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:verification')
        ->name('verification.send');

    Route::put('/phone', [PhoneVerificationController::class, 'update'])
        ->middleware('throttle:phone-otp')
        ->name('verification.phone.update');
    Route::post('/phone/verification-code', [PhoneVerificationController::class, 'send'])
        ->middleware('throttle:phone-otp')
        ->name('verification.phone.send');
    Route::post('/phone/verification', [PhoneVerificationController::class, 'confirm'])
        ->middleware('throttle:phone-otp')
        ->name('verification.phone.confirm');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
