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
        'session_start' => '16:00',   // reservation card: sessions from 4:00 PM
        'session_end'   => '21:30',   // reservation card: to 9:30 PM
        'cafe_end'      => '23:00',   // cafe site: cafe open until 11 PM
        'closed_days'   => [0],       // Sunday
    ],

    'group_discount' => [
        'min_participants' => 4,
        'percentage'       => 10,
    ],

    // PHASE 12: the booking-fee split. Never hard-code 50 anywhere else.
    'booking_fee_percentage' => env('BOOKING_FEE_PERCENTAGE', 50),
    'payment_deadline_hours' => env('PAYMENT_DEADLINE_HOURS', 48),

    // PHASE 14: cafe credit issued with the entry fee. The printed menu says
    // it is redeemable against food and drinks only.
    'cafe_credit' => [
        'entry_fee_coupon' => 50,
        'per_participant'  => true,   // AWAITING YOUR CONFIRMATION
    ],

    'contact' => [
        'email'    => 'artcafe.shunno@gmail.com',
        'phone'    => '+8801799020731',
        'whatsapp' => '8801711532891',
        'address'  => '5/6 Block F, Lalmatia, Dhaka 1207, Bangladesh',
        'maps'     => 'https://maps.app.goo.gl/ZCaYdveECmxbiScz8',
    ],

];
