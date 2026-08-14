<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Public\AvailabilityController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\ReservationRequestController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Core Entry Point)
|--------------------------------------------------------------------------
|
| Kept deliberately minimal. Only the public auth entry, the dashboard and
| a few core/session routes live here. Everything else is split out and
| loaded from bootstrap/app.php via the `then:` closure:
|
| routes/
| ├── web.php          # This file — public + core authenticated routes
| ├── auth.php         # Guest auth routes (forgot / reset password)
| └── admin.php        # Admin panel routes

*/

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| The landing page carries the explanation; the reservation request lives in a
| popup on top of it. There is deliberately no /reservation page.
|
| Deep link: /?reserve=1 opens the popup on load and then cleans the query
| string. That is what the QR code on the printed reservation card and the
| "request a visit" links in emails should point at.
|
| PHASE 7C: every throttle here is now a NAMED limiter defined in
| App\Providers\RateLimitServiceProvider. The inline form (`throttle:8,1`) keys
| on domain and IP only — not on the route — so all three of these shared a
| single counter and the availability polling from the popup was exhausting the
| reservation allowance. Never use the inline form on more than one route in
| this application without also passing a distinct third argument.
*/

Route::get('/', [LandingController::class, 'index'])->name('home');

Route::post('/reservation/request', [ReservationRequestController::class, 'store'])
    ->middleware('throttle:reservations')
    ->name('reservation.request.store');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

/*
| Availability. Two read-only endpoints feeding the popup:
|   /availability          — start times for one session on one date
|   /availability/calendar — one month of days, each marked bookable or not
|
| The calendar endpoint is what lets the date field grey out Sundays, blocked
| dates and days too short for the chosen session. Both are conveniences;
| StoreReservationRequest re-runs AvailabilityService on submit and that is the
| check that decides.
*/
Route::middleware('throttle:availability')
    ->group(function () {
        Route::get('availability', [AvailabilityController::class, 'slots'])
            ->name('availability');

        Route::get('availability/calendar', [AvailabilityController::class, 'calendar'])
            ->name('availability.calendar');
    });

/*
|--------------------------------------------------------------------------
| Mail Test (DEV ONLY)
|--------------------------------------------------------------------------
| Quick mailer smoke-test. REMOVE before deploying to production.
| Still on the Phase 17 list.
*/
Route::get('/send-test-email', function () {
    Mail::raw('This is a test email!', function ($message) {
        $message->to('webweaverashik@gmail.com')->subject('Test Email');
    });
    return 'Test email sent!';
});
