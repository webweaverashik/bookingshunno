<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Public\AvailabilityController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\PaymentGatewayController;
use App\Http\Controllers\Public\PaymentPortalController;
use App\Http\Controllers\Public\ReservationRequestController;
use App\Http\Controllers\PayslipController;
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
| PHASE 12B — the visitor's payslip
|--------------------------------------------------------------------------
| No login. The 48-character token on the payment IS the credential, which is
| how payment links work everywhere — the alternative is asking somebody to
| authenticate before they can look at a receipt for money they have already
| paid.
|
| Nothing here reveals more than the visitor already holds in their own email,
| and the page is read-only: there is no action on it that could be taken by
| whoever the link was forwarded to.
|
| Throttled on its own named limiter rather than the inline form. PHASE 7C
| established why: the inline `throttle:n,m` keys on domain and IP only, so two
| routes using it share one counter.
*/
/*
| The payment portal. Same token, same reasoning as the payslip below: the URL
| is the credential and the page is read-only, so a forwarded link exposes
| nothing the visitor did not already have in their email.
|
| Phase 13 attaches the gateway to the Pay button and Phase 14 attaches voucher
| redemption. Both are inert here and say so on the page.
*/
Route::get('pay/{token}', [PaymentPortalController::class, 'show'])
    ->middleware('throttle:payslip')
    ->name('payment.portal');

/*
| SSLCommerz. Four of these five are entered by somebody with no session,
| carrying data we did not write.
|
| start() is ours and keeps the web middleware. The three browser callbacks come
| back as POST from another domain with no CSRF token, and the IPN arrives
| server-to-server with no browser at all — all four are exempted in
| bootstrap/app.php. The exemption is safe precisely because none of them are
| trusted: they name an attempt, and SslCommerzService::validate() decides over
| a separate connection whether it was paid.
|
| Named `payment.gateway.*` and built with route() inside the initiation
| payload, so these URLs are registered with SSLCommerz from one place.
*/
Route::prefix('payment/gateway')->name('payment.gateway.')->group(function () {
    // POST, not GET. Opening a checkout session creates a database row and
    // calls out to SSLCommerz, so it must not be reachable by a link, a
    // prefetch, or a refresh.
    Route::post('start/{token}', [PaymentGatewayController::class, 'start'])
        ->middleware('throttle:payslip')
        ->name('start');

    Route::match(['get', 'post'], 'success', [PaymentGatewayController::class, 'success'])->name('success');
    Route::match(['get', 'post'], 'fail', [PaymentGatewayController::class, 'fail'])->name('fail');
    Route::match(['get', 'post'], 'cancel', [PaymentGatewayController::class, 'cancel'])->name('cancel');

    Route::post('ipn', [PaymentGatewayController::class, 'ipn'])->name('ipn');
});

Route::get('receipt/{token}/{transaction:reference}', [PayslipController::class, 'visitor'])
    ->middleware('throttle:payslip')
    ->name('payslip');

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
