<?php

use App\Http\Controllers\Public\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('home');

/*
 * PHASE 2: replace with ReservationInfoController@index.
 *
 * This is a real holding page rather than a redirect. The earlier version used
 * redirect()->route('home', ['#how']), which Laravel treats as a query
 * parameter and renders as ?%23how — the CTA is better landing somewhere
 * honest than bouncing back to an anchor on the page it came from.
 */
Route::view('/reservation', 'public.reservation-placeholder')->name('reservation.info');
