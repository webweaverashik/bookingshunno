<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE 17 — the headers every response should have been carrying.
 *
 * These replace the frame-busting script that has been sitting in the admin
 * <head> since Phase 5, and they close a token leak that has been live since
 * Phase 12. Taken one at a time.
 *
 * ---------------------------------------------------------------------------
 * 1. FRAMING — why the script it replaces did not work
 * ---------------------------------------------------------------------------
 * layouts/partials/head.blade.php ran:
 *
 *     if (window.top != window.self) window.top.location.replace(...)
 *
 * That is defeated by one attribute. An attacker frames the panel with
 * <iframe sandbox="allow-scripts">, which permits the script to run but forbids
 * it from navigating the top window. The assignment fails silently, the script
 * carries on, and the page renders inside the frame exactly as before — with
 * the defence appearing to be present. It also only ever protected the ADMIN
 * layout: the public site, which now carries the payment portal and the voucher
 * redemption form, had nothing at all.
 *
 * A header cannot be sandboxed away. The browser refuses to build the frame
 * before any of our code runs. Both headers are sent because X-Frame-Options is
 * what older browsers honour and frame-ancestors is what the CSP standard
 * replaced it with; where both are understood, CSP wins.
 *
 * ---------------------------------------------------------------------------
 * 2. REFERRER — a live credential leak
 * ---------------------------------------------------------------------------
 * The payment portal, the payslip and the gateway routes all put a 48-character
 * token IN THE URL PATH. That token is the credential — Phase 12 chose that
 * deliberately and the reasoning is sound. But a browser's default referrer
 * behaviour sends the FULL URL, path included, to any external site the visitor
 * navigates to next. The token travels in a Referer header to whoever they
 * clicked through to, and it stays in that site's access logs.
 *
 * So token-bearing routes get `no-referrer`: nothing leaves at all. They load
 * no third-party resources, so it costs nothing. Everywhere else gets
 * strict-origin-when-cross-origin, which sends the origin but never the path.
 *
 * ---------------------------------------------------------------------------
 * 3. NOSNIFF — and what it protects in Phase 16
 * ---------------------------------------------------------------------------
 * The CSV export declares text/csv, and its rows contain visitor names typed by
 * the public. Without nosniff a content-sniffing browser is entitled to look
 * inside, decide the bytes look like HTML, and render them — which turns a name
 * field into stored XSS delivered through a download. CsvStream already guards
 * cells against spreadsheet formulas; this guards the same cells against the
 * browser.
 *
 * ---------------------------------------------------------------------------
 * WHAT IS DELIBERATELY NOT HERE
 * ---------------------------------------------------------------------------
 * A full Content-Security-Policy. Metronic's bundle relies on inline scripts
 * and inline styles throughout, and the public site has inline JSON config
 * blocks; a real script-src would need 'unsafe-inline', which is a policy that
 * looks like protection and provides almost none. Doing it properly means
 * nonces threaded through both layouts, which is a phase of its own rather than
 * a line in this file. frame-ancestors is included because it is the one
 * directive that needs no inventory of what the pages load.
 */
class SecurityHeaders
{
    /**
     * Routes whose URL contains a secret.
     *
     * Matched on route NAME rather than path, so renaming a URL cannot quietly
     * drop the protection.
     */
    private const TOKEN_ROUTES = [
        'payment.portal',
        'payslip',
        'payment.voucher.check',
        'payment.voucher.apply',
        'payment.gateway.start',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        /*
         | Cross-origin requests get the origin and nothing more, so a token in
         | a path never leaves the site. Token pages get nothing at all.
         */
        $response->headers->set(
            'Referrer-Policy',
            $this->carriesToken($request) ? 'no-referrer' : 'strict-origin-when-cross-origin'
        );

        /*
         | Features this application has no use for, switched off so that an
         | injected script cannot reach them either.
         */
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        if (! $this->mayBeFramed($request)) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        $this->applyHsts($request, $response);

        return $response;
    }

    private function carriesToken(Request $request): bool
    {
        $name = $request->route()?->getName();

        return $name !== null && in_array($name, self::TOKEN_ROUTES, true);
    }

    /**
     * The one exemption, and it is a guess worth stating plainly.
     *
     * SSLCommerz's hosted checkout takes over the whole window, so its three
     * browser callbacks come back top-level and DENY is correct for them. But
     * the gateway also offers an embedded mode, and if the client's account is
     * ever switched to it those callbacks would land inside an SSLCommerz
     * frame — where DENY shows the visitor a blank page immediately after they
     * have paid, which is the worst possible moment to break.
     *
     * Exempting them costs nothing: all three are redirect-only, carry no
     * markup, and expose no control that a clickjack could aim at. The visible
     * result of paying is the portal page they are sent on to, and that page is
     * protected.
     */
    private function mayBeFramed(Request $request): bool
    {
        $name = $request->route()?->getName();

        return in_array($name, [
            'payment.gateway.success',
            'payment.gateway.fail',
            'payment.gateway.cancel',
        ], true);
    }

    /**
     * HSTS, off by default and on a short clock when enabled.
     *
     * This is the one header here that can lock people out. Once a browser has
     * seen it, it REFUSES plain HTTP to this host for the full max-age and
     * there is no way to call that back from the server — if the certificate
     * lapses on shared hosting, the site is unreachable rather than insecure
     * until it is fixed.
     *
     * So: opt-in through .env, and a max-age that starts small. The sequence
     * that avoids trouble is 300, then a day, then a week, then a year, moving
     * on only once the certificate has renewed itself at least once. No
     * preload directive — that is a submission to a browser-vendor list which
     * takes months to undo.
     */
    private function applyHsts(Request $request, Response $response): void
    {
        if (! $request->secure() || ! config('shunno.security.hsts_enabled', false)) {
            return;
        }

        $maxAge = (int) config('shunno.security.hsts_max_age', 300);

        $response->headers->set(
            'Strict-Transport-Security',
            "max-age={$maxAge}; includeSubDomains"
        );
    }
}
