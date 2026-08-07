<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('register/verify-otp', [OtpVerificationController::class, 'show'])->name('otp.verify');
    Route::post('register/verify-otp', [OtpVerificationController::class, 'verify'])->name('otp.verify.store');
    Route::post('register/resend-otp', [OtpVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')
        ->name('otp.resend');
    Route::get('register/pending-approval', [OtpVerificationController::class, 'pendingApproval'])
        ->name('otp.pending-approval');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');

    Route::get('proud-admin', [AuthenticatedSessionController::class, 'adminCreate'])->name('admin.login');
    Route::post('adm/login', [AuthenticatedSessionController::class, 'adminStore'])
        ->middleware('throttle:20,1');

    Route::get('sso/{provider}/redirect', [SsoController::class, 'redirectToProvider'])->name('sso.redirect');
    Route::get('sso/callback', [SsoController::class, 'handleProviderCallback'])->name('sso.callback');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('sso/logout', [SsoController::class, 'logout'])->name('sso.logout');
});
