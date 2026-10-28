<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Auth\AuthenticatedSessionController;
use Nreljed\Platform\Http\Controllers\Auth\ConfirmablePasswordController;
use Nreljed\Platform\Http\Controllers\Auth\EmailVerificationNotificationController;
use Nreljed\Platform\Http\Controllers\Auth\EmailVerificationPromptController;
use Nreljed\Platform\Http\Controllers\Auth\NewPasswordController;
use Nreljed\Platform\Http\Controllers\Auth\PasswordController;
use Nreljed\Platform\Http\Controllers\Auth\PasswordResetLinkController;
use Nreljed\Platform\Http\Controllers\Auth\RegisteredUserController;
use Nreljed\Platform\Http\Controllers\Auth\VerifyEmailController;

Route::get('register/check-availability', [RegisteredUserController::class, 'checkAvailability'])
    ->middleware(['throttle:10,1', 'maintenance'])
    ->name('register.availability');

// Signup is closed while the doors are closed: an account created during a
// maintenance window could not be used anyway.
Route::middleware(['guest', 'maintenance'])->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register');
});

// Signing in is deliberately NOT behind `maintenance`.
//
// It used to be, which made maintenance mode a lockout trap: admins bypass the
// middleware, but only while their session is alive (120 minutes by default).
// Once it expired they could not reach `/login` at all, and the documented way
// back - the emailed recovery link - depends on outbound mail, which on a
// shared host is routinely log-only or queued to a worker nobody runs. The
// result was a site owner locked out of their own admin by their own
// maintenance flag, with the bypass token readable only from the admin UI they
// could no longer open.
//
// The login form leaks nothing (no content, no catalog) and an authenticated
// non-admin still gets the 503 on every dashboard and public page, so the
// maintenance page keeps doing its job. Nothing else is exempt: `register`,
// the dashboard, the storefront and the blog all stay gated.
Route::middleware(['guest'])->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('password.store');
});

Route::middleware(['auth', 'maintenance'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('email/verified', fn () => Inertia::render('auth/email-verified'))->name('verification.verified');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:auth');

    Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:auth')->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('throttle:auth')
        ->name('logout');
});
