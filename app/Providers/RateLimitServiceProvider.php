<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * PHASE 7C — why this file exists.
 *
 * The inline form of the throttle middleware — `throttle:8,1` — does NOT key on
 * the route. Illuminate\Routing\Middleware\ThrottleRequests::resolveRequestSignature()
 * returns sha1($route->getDomain() . '|' . $request->ip()) and handle() prefixes
 * it with an empty string, so every route that used the inline form shared one
 * counter per visitor and the smallest limit on any of them won.
 *
 * That is what broke the reservation form. The popup calls GET /availability
 * each time the session or the date changes. Nine of those in a minute filled
 * the shared bucket, and the next POST to /reservation/request was measured
 * against its own limit of 8, found the bucket already over, and returned 429 —
 * "That is a lot of requests in a short time." The form was never actually
 * submitted, so nothing reached validation and nothing was logged.
 *
 * Named limiters are keyed as md5($limiterName . $limit->key), so each of the
 * three below gets its own bucket and the `by()` prefixes keep them apart even
 * if the names ever collide.
 *
 * REGISTER THIS PROVIDER: add RateLimitServiceProvider::class to the array in
 * bootstrap/providers.php.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         | Reservation requests. Two limits together: a burst ceiling that still
         | leaves room for a visitor who mistypes an email and resubmits three
         | or four times, and an hourly ceiling that stops a script sitting on
         | the endpoint all afternoon.
         */
        RateLimiter::for('reservations', fn (Request $request) => [
            Limit::perMinute(6)->by('reservation:' . $request->ip()),
            Limit::perHour(30)->by('reservation-hour:' . $request->ip()),
        ]);

        /*
         | Availability lookups. Read-only, cheap, and fired on every date and
         | session change — a visitor clicking through a calendar legitimately
         | makes a lot of these. Generous on purpose; it is here to stop
         | scraping, not to police normal use.
         */
        RateLimiter::for('availability', fn (Request $request) => Limit::perMinute(240)
            ->by('availability:' . $request->ip()));

        /*
         | Auth. Kept tight, and now genuinely isolated: a visitor filling in the
         | reservation popup can no longer burn through the login allowance, and
         | a login attempt can no longer be refused because of it.
         */
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by('login:' . strtolower((string) $request->input('email')) . '|' . $request->ip()));

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(10)
            ->by('otp:' . $request->ip()));

        RateLimiter::for('otp-resend', fn (Request $request) => Limit::perMinute(3)
            ->by('otp-resend:' . $request->ip()));
    }
}
