<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\WorkshopController;
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
    ->middleware(['auth', 'isLoggedIn', 'role:Admin|Manager'])
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

        /*
        |----------------------------------------------------------------------
        | PHASE 6 — Workshops
        |----------------------------------------------------------------------
        | Bound on {workshop:id}, not the slug: the slug is editable, and an
        | admin who renames a workshop should not find the edit URL they are
        | sitting on has stopped resolving.
        |
        | The group is gated on workshops.view; WorkshopPolicy handles the
        | write abilities per action, so Manager reaches the page read-only
        | rather than being bounced to a 403.
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

        // PHASE 9:  reservations  (list, show, approve, decline, request-info)
        // PHASE 8:  visitors
        // PHASE 7:  availability
        // PHASE 12: payments
        // PHASE 14: vouchers
        // PHASE 16: reports
    });
