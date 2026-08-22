<?php

/*
|--------------------------------------------------------------------------
| Shunno Art Cafe — business configuration
|--------------------------------------------------------------------------
| The values most likely to change without a code rewrite. Anything the admin
| should edit through the UI moves to the `settings` table in Phase 4; this
| array then stays as the fallback default.
*/

return [
    'currency' => 'BDT',

    'operating' => [
        'session_start' => '16:00', // reservation card: sessions from 4:00 PM
        'session_end' => '21:30', // reservation card: to 9:30 PM
        'cafe_end' => '23:00', // cafe site: cafe open until 11 PM
        'closed_days' => [0], // Sunday
    ],

    'group_discount' => [
        'min_participants' => 4,
        'percentage' => 10,
    ],

    // The booking-fee split. Never hard-code 50 anywhere else.
    'booking_fee_percentage' => env('BOOKING_FEE_PERCENTAGE', 50),
    'payment_deadline_hours' => env('PAYMENT_DEADLINE_HOURS', 48),

    'payments' => [
        /*
         | How long before a deadline the reminder goes out.
         |
         | 24 hours against a 48-hour deadline, so it lands roughly halfway:
         | late enough that somebody who was always going to pay today has
         | already done so and never sees it, early enough that it is still
         | actionable. Raising this above payment_deadline_hours would send the
         | reminder before the request itself, which is why the command's
         | --hours option exists for testing rather than a second default.
         */
        'reminder_hours' => env('PAYMENT_REMINDER_HOURS', 24),

        /*
         | How long after a deadline before the reservation is cancelled.
         |
         | Not zero. SSLCommerz confirms by redirect AND by IPN and the IPN can
         | lag; a counter payment lags by however long somebody takes to type it
         | in. Cancelling at the stroke of the deadline would eventually cancel
         | a booking that had just been paid for.
         |
         | Not a database setting yet — promoting it to the payments settings
         | screen is a form field, a validation rule and a SettingController
         | line, and is worth doing if the studio ever wants to tune it.
         */
        'expiry_grace_hours' => env('PAYMENT_EXPIRY_GRACE_HOURS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response headers
    |--------------------------------------------------------------------------
    | Read by App\Http\Middleware\SecurityHeaders. Only HSTS is configurable;
    | the rest are unconditional because there is no situation in which this
    | application wants to be framed, sniffed, or leaking a payment token in a
    | Referer header.
    */
    'security' => [
        /*
         | HSTS is OFF by default, and that is not timidity.
         |
         | Once a browser has seen this header it refuses plain HTTP to the host
         | for the whole max-age, and nothing the server sends can call that
         | back. On shared hosting, a certificate that fails to renew then makes
         | the site unreachable rather than merely insecure — for a year, if the
         | max-age said a year.
         |
         | Turn it on once HTTPS is confirmed working, and climb:
         |     300  (5 min)  ->  86400  (a day)  ->  604800  (a week)  ->  31536000
         | moving up only after the certificate has auto-renewed at least once.
         */
        'hsts_enabled' => env('HSTS_ENABLED', false),
        'hsts_max_age' => env('HSTS_MAX_AGE', 300),
    ],

    // Cafe credit issued with the entry fee. The printed menu says
    // it is redeemable against food and drinks only.
    'cafe_credit' => [
        'entry_fee_coupon' => 50,
        'per_participant' => true, // AWAITING YOUR CONFIRMATION
    ],

    'contact' => [
        'email' => 'artcafe.shunno@gmail.com',
        'phone' => '+8801799020731',
        'whatsapp' => '8801711532891',
        'address' => '5/6 Block F, Lalmatia, Dhaka 1207, Bangladesh',
        'maps' => 'https://maps.app.goo.gl/ZCaYdveECmxbiScz8',
    ],
];
