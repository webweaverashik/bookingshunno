<?php

use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BlockedDateController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\ReservationDecisionController;
use App\Http\Controllers\Admin\VisitorController;
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
        | PHASE 9 — Reservations
        | PHASE 10 — Approval workflow
        |----------------------------------------------------------------------
        | Bound on the reference code, which is Reservation's route key: an
        | admin pasting SHN-2608-A7K3 from an email lands on the right record,
        | and the sequential id stays out of the URL.
        |
        | Gated on reservations.view for the whole group; ReservationPolicy
        | handles every per-action ability, because the decisions each depend on
        | BOTH a permission and where the reservation currently sits. Route
        | middleware cannot express the second half, so there is deliberately no
        | permission middleware on the decision routes — the policy is the gate,
        | and it is called in the controller.
        */
        Route::prefix('reservations')
            ->name('reservations.')
            ->middleware('permission:reservations.view')
            ->group(function () {
                Route::get('/', [ReservationController::class, 'index'])->name('index');
                Route::get('list', [ReservationController::class, 'list'])->name('list');
                Route::get('{reservation}', [ReservationController::class, 'show'])->name('show');

                Route::middleware('permission:reservations.update')->group(function () {
                    Route::get('{reservation}/edit', [ReservationController::class, 'edit'])->name('edit');
                    Route::get('{reservation}/slots', [ReservationController::class, 'slots'])->name('slots');
                    Route::post('{reservation}', [ReservationController::class, 'update'])->name('update');
                });

                // Decisions. Authorised by ReservationPolicy inside the
                // controller — see the note above.
                Route::post('{reservation}/approve', [ReservationDecisionController::class, 'approve'])->name('approve');
                Route::post('{reservation}/escalate', [ReservationDecisionController::class, 'escalate'])->name('escalate');
                Route::post('{reservation}/decline', [ReservationDecisionController::class, 'decline'])->name('decline');
                Route::post('{reservation}/request-info', [ReservationDecisionController::class, 'requestInfo'])->name('request-info');
                Route::post('{reservation}/return-to-review', [ReservationDecisionController::class, 'returnToReview'])->name('return-to-review');
                Route::post('{reservation}/cancel', [ReservationDecisionController::class, 'cancel'])->name('cancel');
            });

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
        | PHASE 7 — Availability
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

        /*
        |----------------------------------------------------------------------
        | PHASE 8 — Visitors
        |----------------------------------------------------------------------
        | Bound on {visitor:id}. No policy class: a policy on
        | App\Models\Auth\User would also govern staff-user management in a
        | later phase, and the two run on different permissions (visitors.*
        | against users.*). Authorisation is therefore by permission name, on
        | the routes and again in the views.
        |
        | Manager holds both visitors.view and visitors.update — they run
        | day-to-day reservation operations and need to fix a mistyped phone
        | number without waiting for an Admin.
        */
        Route::prefix('visitors')
            ->name('visitors.')
            ->middleware('permission:visitors.view')
            ->group(function () {
                Route::get('/', [VisitorController::class, 'index'])->name('index');
                Route::get('list', [VisitorController::class, 'list'])->name('list');
                Route::get('{visitor:id}', [VisitorController::class, 'show'])->name('show');

                Route::middleware('permission:visitors.update')->group(function () {
                    Route::get('{visitor:id}/edit', [VisitorController::class, 'edit'])->name('edit');
                    Route::post('{visitor:id}', [VisitorController::class, 'update'])->name('update');
                });
            });

        // PHASE 12: payments
        // PHASE 14: vouchers
        // PHASE 16: reports
    });
