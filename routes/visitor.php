<?php

use App\Http\Controllers\Visitor\VisitorAreaController;
use App\Http\Controllers\Visitor\VisitorAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The returning visitor
|--------------------------------------------------------------------------
| Its own file, loaded from bootstrap/app.php beside routes/admin.php. Kept out
| of web.php because web.php is the public front door and is already long, and
| kept out of auth.php because that file is the STAFF password-reset flow and
| mixing the two invites exactly the confusion these routes exist to prevent.
|
| /visits rather than /account or /dashboard. It is the word the emails and the
| landing page already use, and it says what is on the page.
|
| NO 'auth' MIDDLEWARE ANYWHERE HERE. Laravel's Authenticate middleware sends a
| guest to route('login'), which is the staff password screen — a dead end for
| a visitor whose account has never had a password they could know. EnsureVisitor
| does the same job and sends them somewhere they can actually get in.
|
| Every throttle is a NAMED limiter. Phase 7C established why: the inline
| `throttle:n,m` form keys on domain and IP only, so two routes using it share
| one bucket and the tightest limit wins for both.
*/

Route::prefix('visits')->name('visitor.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Getting in
    |--------------------------------------------------------------------------
    | Guests only, enforced inside the controller rather than by the `guest`
    | middleware: that middleware redirects to a fixed home constant, and a
    | signed-in Admin who lands here should go to the panel, not to the public
    | site. bounceIfSignedIn() knows the difference.
    */
    Route::get('sign-in', [VisitorAuthController::class, 'showSignIn'])->name('login');

    Route::post('sign-in', [VisitorAuthController::class, 'sendCode'])
        ->middleware('throttle:visitor-login')
        ->name('login.send');

    Route::get('verify', [VisitorAuthController::class, 'showVerify'])->name('verify');

    Route::post('verify', [VisitorAuthController::class, 'verify'])
        ->middleware('throttle:visitor-otp')
        ->name('verify.submit');

    Route::post('verify/resend', [VisitorAuthController::class, 'resend'])
        ->middleware('throttle:visitor-otp-resend')
        ->name('verify.resend');

    // Outside the visitor middleware: signing out of a session that has already
    // gone stale should still work rather than bouncing to the sign-in page.
    Route::post('sign-out', [VisitorAuthController::class, 'signOut'])->name('logout');

    /*
    |--------------------------------------------------------------------------
    | The area itself
    |--------------------------------------------------------------------------
    | 'account' is registered BEFORE '{reservation}' so the literal wins. Same
    | ordering rule as the 'list' endpoints in routes/admin.php — without it,
    | /visits/account would be looked up as a reference code.
    */
    Route::middleware('visitor')->group(function () {
        Route::get('/', [VisitorAreaController::class, 'index'])->name('index');

        Route::get('account', [VisitorAreaController::class, 'account'])->name('account');
        Route::post('account', [VisitorAreaController::class, 'updateAccount'])->name('account.update');

        Route::get('{reservation}', [VisitorAreaController::class, 'show'])->name('show');
    });
});
