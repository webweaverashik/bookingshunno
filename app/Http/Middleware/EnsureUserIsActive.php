<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces the IsLoggedIn middleware from the BIDA template, which duplicated
 * Laravel's own `auth` and did nothing extra. This adds the part that was
 * missing: a session that outlives a deactivation is signed out immediately
 * rather than at its natural expiry.
 *
 * Register alongside `auth`, not instead of it.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || $user->trashed())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is no longer active. Please contact an administrator.',
            ]);
        }

        return $next($request);
    }
}
