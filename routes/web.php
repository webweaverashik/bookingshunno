<?php

use App\Http\Controllers\Auth\AuthController;
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
*/

Route::get('/', [LandingController::class, 'index'])->name('home');

Route::post('/reservation/request', [ReservationRequestController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('reservation.request.store');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

/*
|--------------------------------------------------------------------------
| Mail Test (DEV ONLY)
|--------------------------------------------------------------------------
| Quick mailer smoke-test. REMOVE before deploying to production.
*/
Route::get('/send-test-email', function () {
    Mail::raw('This is a test email!', function ($message) {
        $message->to('webweaverashik@gmail.com')->subject('Test Email');
    });
    return 'Test email sent!';
});
