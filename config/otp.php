<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login OTP
    |--------------------------------------------------------------------------
    | The email one-time-password challenge.
    |
    | IMPORTANT: `staff.enabled` is a convenience switch. There is deliberately
    | no equivalent for visitors — under the single-guard design a visitor has
    | no usable password, so OTP is their only factor and disabling it would
    | lock every visitor out of their own reservations permanently.
    */

    'staff' => [
        'enabled' => filter_var(env('OTP_STAFF_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'length'       => (int) env('OTP_LENGTH', 6),
    'expires_in'   => (int) env('OTP_EXPIRES_IN', 5),      // minutes

    // Wrong guesses allowed against a single code before it is voided.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Wrong guesses allowed across the whole pending login, resends included.
    // Without this the per-code cap is theatre: five guesses, resend, five more.
    'max_total_attempts' => (int) env('OTP_MAX_TOTAL_ATTEMPTS', 12),

    // Codes a visitor may request for one pending login.
    'max_resends'  => (int) env('OTP_MAX_RESENDS', 4),

    'resend_after' => (int) env('OTP_RESEND_AFTER', 60),   // seconds

    // How long a half-finished login may sit in the session. The code expires
    // in 5 minutes, but without this a pending login survives the full session
    // lifetime and can be resent from indefinitely.
    'pending_login_ttl' => (int) env('OTP_PENDING_TTL', 15),  // minutes

];
