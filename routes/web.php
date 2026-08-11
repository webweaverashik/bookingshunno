<?php

use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\ReservationRequestController;
use Illuminate\Support\Facades\Route;

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
