<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication and admin routes
|--------------------------------------------------------------------------
| Register in bootstrap/app.php:
|
|   ->withRouting(
|       web: __DIR__.'/../routes/web.php',
|       then: fn () => Route::middleware('web')->group(base_path('routes/admin.php')),
|   )
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // The OTP step runs before the user is authenticated, so it sits in the
    // guest group; AuthController checks the pending-login session itself.
    Route::get('/verify', [AuthController::class, 'showOtp'])->name('otp.show');
    Route::post('/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('otp.verify');
    Route::post('/verify/resend', [AuthController::class, 'resendOtp'])
        ->middleware('throttle:5,10')
        ->name('otp.resend');

    Route::get('/forgot-password', [PasswordController::class, 'showLinkRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendResetLink'])
        ->middleware('throttle:5,10')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:5,10')
        ->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active', 'role:Admin|Manager'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // PHASE 9:  reservations  (list, show, approve, decline, request-info)
        // PHASE 8:  visitors
        // PHASE 6:  workshops
        // PHASE 7:  availability
        // PHASE 12: payments
        // PHASE 14: vouchers
        // PHASE 16: reports
    });
