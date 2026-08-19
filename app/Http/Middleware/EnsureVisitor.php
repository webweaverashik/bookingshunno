<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate on /visits.
 *
 * A separate middleware rather than Laravel's `auth`, for one reason: `auth`
 * sends a guest to route('login'), which is the STAFF sign-in screen. A visitor
 * following a link from their confirmation email would land on a password form
 * for an account whose password they have never been told, because
 * ReservationService::resolveVisitor() creates visitor accounts with a random
 * one. They would be stuck.
 *
 * The other two checks are about keeping the two audiences apart:
 *
 *   Staff are pushed to the admin panel. Not forbidden — nothing here is
 *   secret from them — but a Manager who lands on the visitor area is almost
 *   certainly looking for the reservation register, and a member of staff who
 *   also books workshops would otherwise see a personal page while holding an
 *   admin session, which is a confusing place to be.
 *
 *   A deactivated account is signed out on the spot. is_active is checked at
 *   sign-in, but a session outlives the check by two weeks, and the point of
 *   deactivating somebody is that they stop having access now rather than at
 *   the end of the fortnight.
 */
class EnsureVisitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            /*
             | Only GETs are worth returning to. Replaying a POST after sign-in
             | would resubmit a form against a session that has since changed
             | hands, and the visitor area has no POST worth resuming anyway.
             */
            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('visitor.login')
                ->with('visitor_notice', 'Sign in to see your visits.');
        }

        if ($user->isStaff()) {
            return redirect()->route('admin.dashboard');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('visitor.login')
                ->with('visitor_error', 'This account is no longer active. Please get in touch and we will sort it out.');
        }

        return $next($request);
    }
}
