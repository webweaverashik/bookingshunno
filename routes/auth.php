<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
| Password reset, forgot password (guest routes)
| Loaded in bootstrap/app.php with 'web' middleware only
*/

Route::controller(PasswordController::class)
    ->middleware('guest')
    ->group(function () {
        // Forgot Password
        Route::get('forgot-password', 'showLinkRequestForm')->name('password.request');
        Route::post('forgot-password', 'sendResetLinkEmail')->name('password.email');

        // Reset Password redirect
        Route::get('reset-password', fn() => redirect()->route('password.request'))->name('password.reset.request');

        // Reset Password Form & Action
        Route::get('reset-password/{token}', 'showResetForm')->name('password.reset');
        Route::post('reset-password', 'reset')->name('password.update');
    });

/*
|--------------------------------------------------------------------------
| Login OTP (two-step verification)
|--------------------------------------------------------------------------
| Step 2 of login. The pending user is carried in the guest session, so
| these stay outside the `auth` middleware.
|
| Named limiters rather than throttle:10,1 / throttle:5,1. The inline
| form keys on domain and IP with no route in the key, so these two shared a
| bucket with the reservation form and with each other — a visitor who had been
| browsing the public site could arrive at the OTP screen already throttled.
*/
Route::get('/login/otp', [AuthController::class, 'showOtp'])->name('login.otp');

Route::post('/login/otp', [AuthController::class, 'verifyOtp'])
    ->middleware('throttle:otp')->name('login.otp.verify');

Route::post('/login/otp/resend', [AuthController::class, 'resendOtp'])
    ->middleware('throttle:otp-resend')->name('login.otp.resend');

/*
|--------------------------------------------------------------------------
| Guest Logout Fallback
|--------------------------------------------------------------------------
| Handles GET /logout when there is no active session — send guests to the
| login screen instead of bouncing back.
*/
Route::get('/logout', fn() => redirect()->route('login'));
