<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes
|--------------------------------------------------------------------------
| Register in bootstrap/app.php:
|
|   ->withRouting(
|       web: __DIR__.'/../routes/web.php',
|       then: fn () => Route::middleware('web')->group(base_path('routes/admin.php')),
|   )
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'active', 'role:Admin|Manager'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Logout (POST performs logout; GET bounces back — used by stray links).
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/logout', fn() => redirect()->back())->name('logout.get');

        // Clear application/server cache (AJAX helper).
        Route::get('clear-cache', function () {
            clearServerCache();
            return response()->json(['success' => true]);
        })->name('clear.cache');

        // PHASE 9:  reservations  (list, show, approve, decline, request-info)
        // PHASE 8:  visitors
        // PHASE 6:  workshops
        // PHASE 7:  availability
        // PHASE 12: payments
        // PHASE 14: vouchers
        // PHASE 16: reports
    });
