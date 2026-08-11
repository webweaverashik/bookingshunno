<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clickjacking and sniffing protection as response headers.
 *
 * The BIDA template carried a frame-busting script in head.blade.php, but the
 * whole `if (window.top != window.self)` body sat after a `//` on one line, so
 * it never executed — the protection has never actually been on. A header is
 * stronger than the JS would have been anyway: it cannot be stripped by an
 * attacker's sandbox attribute.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // frame-ancestors is the modern equivalent of X-Frame-Options and is
        // the one that actually applies in current browsers.
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

        return $response;
    }
}
