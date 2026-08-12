<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Authentication + admin panel. routes/web.php stays public-only.
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Replaces `isLoggedIn`, which duplicated Laravel's own `auth` and
            // added nothing. This one signs out an account that is deactivated
            // mid-session instead of waiting for the session to expire.
            // Use it alongside `auth`, not instead of it.
            'active'             => EnsureUserIsActive::class,

            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // PHASE 13: SSLCommerz posts its IPN server-to-server and carries no
        // session, so that one route has to be CSRF-exempt. Restrict it by
        // gateway IP at the same time — an unauthenticated, unprotected
        // endpoint that marks payments as received is not something to leave
        // open.
        // $middleware->validateCsrfTokens(except: ['payment/ipn']);

        // Production sits behind a proxy. Without this, $request->ip() records
        // the proxy for every visitor, which breaks both the per-IP rate
        // limiting on login and the submitted_ip on reservations.
        // $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {

        /*
        |----------------------------------------------------------------------
        | One JSON shape for every AJAX failure
        |----------------------------------------------------------------------
        | §18 of the brief fixes the envelope as { success, message, errors }.
        | Laravel's defaults return { message, errors } with no `success` key,
        | so without these handlers the front end would need a special case per
        | status code. Registered here once instead.
        */

        $wantsJson = fn(Request $request): bool => $request->expectsJson() || $request->ajax();

        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null; // fall through to the normal redirect-with-errors
            }

            // The auth screens surface `message` only, so send the first
            // real validation message rather than a generic heading they
            // would show instead of the actual problem.
            $first = collect($e->errors())->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $first ?: 'Please correct the highlighted fields.',
                'errors'  => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'That is a lot of requests in a short time. Please wait a moment and try again.',
            ], 429);
        });

        /*
        |----------------------------------------------------------------------
        | Session expiry
        |----------------------------------------------------------------------
        */
        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($wantsJson) {
            if ($wantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session has expired. Please reload the page and try again.',
                ], 419);
            }

            return redirect()->route('login')
                ->with('error', 'Your session expired due to inactivity. Please sign in again.');
        });

        /*
        |----------------------------------------------------------------------
        | Unauthorised access
        |----------------------------------------------------------------------
        | Covers Spatie's role:/permission: middleware and Laravel's own can:
        | and Gate::authorize().
        */
        $redirectUnauthorized = function (Request $request) use ($wantsJson) {
            if ($wantsJson($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorised to perform this action.',
                ], 403);
            }

            // Guests fall through to the default handling, which sends them to
            // the sign-in page with an intended URL.
            if (! $request->user()) {
                return null;
            }

            // A Visitor who wanders into /admin must NOT be sent to the admin
            // dashboard: they cannot see it either, so the redirect would bounce
            // straight back here and loop. Send them to the public site.
            if (! $request->user()->isStaff()) {
                return redirect()->route('home');
            }

            // Defensive: never redirect the dashboard to itself.
            if ($request->routeIs('admin.dashboard')) {
                abort(403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'You are not authorised to open that page.');
        };

        $exceptions->render(function (UnauthorizedException $e, Request $request) use ($redirectUnauthorized) {
            return $redirectUnauthorized($request);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($redirectUnauthorized) {
            return $redirectUnauthorized($request);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($redirectUnauthorized) {
            return $redirectUnauthorized($request);
        });

        // Never leak an exception message to a public AJAX caller.
        $exceptions->dontReport([]);
    })
    ->create();
