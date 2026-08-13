<?php

use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BlockedDateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\WorkshopController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'isLoggedIn', 'role:Admin|Manager'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/logout', fn() => redirect()->back())->name('logout.get');

        Route::get('clear-cache', function () {
            clearServerCache();
            return response()->json(['success' => true]);
        })->name('clear.cache');

        /*
        |----------------------------------------------------------------------
        | PHASE 6 — Workshops
        |----------------------------------------------------------------------
        */
        Route::prefix('workshops')
            ->name('workshops.')
            ->middleware('permission:workshops.view')
            ->group(function () {
                Route::get('/', [WorkshopController::class, 'index'])->name('index');
                Route::get('rows', [WorkshopController::class, 'rows'])->name('rows');
                Route::post('/', [WorkshopController::class, 'store'])->name('store');
                Route::get('{workshop:id}/edit', [WorkshopController::class, 'edit'])->name('edit');
                Route::post('{workshop:id}', [WorkshopController::class, 'update'])->name('update');
                Route::post('{workshop:id}/toggle', [WorkshopController::class, 'toggle'])->name('toggle');
                Route::delete('{workshop:id}', [WorkshopController::class, 'destroy'])->name('destroy');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 7B — Availability
        |----------------------------------------------------------------------
        | Gated on availability.view; BlockedDatePolicy handles the write
        | abilities per action. Manager holds view only and so reaches the page
        | read-only rather than being bounced to a 403.
        |
        | POST rather than PUT/PATCH throughout, matching the workshops module:
        | one verb across the admin panel is one fewer thing to get wrong.
        */
        Route::prefix('availability')
            ->name('availability.')
            ->middleware('permission:availability.view')
            ->group(function () {
                Route::get('/', [AvailabilityController::class, 'index'])->name('index');
                Route::post('hours', [AvailabilityController::class, 'updateHours'])->name('hours');
                Route::post('rules', [AvailabilityController::class, 'updateRules'])->name('rules');

                Route::prefix('blocked')
                    ->name('blocked.')
                    ->group(function () {
                        Route::post('/', [BlockedDateController::class, 'store'])->name('store');
                        Route::get('{blockedDate:id}/edit', [BlockedDateController::class, 'edit'])->name('edit');
                        Route::post('{blockedDate:id}', [BlockedDateController::class, 'update'])->name('update');
                        Route::delete('{blockedDate:id}', [BlockedDateController::class, 'destroy'])->name('destroy');
                    });
            });

        // PHASE 9:  reservations  (list, show, approve, decline, request-info)
        // PHASE 8:  visitors
        // PHASE 12: payments
        // PHASE 14: vouchers
        // PHASE 16: reports
    });
