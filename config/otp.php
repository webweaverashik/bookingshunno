<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login OTP Settings
    |--------------------------------------------------------------------------
    | Controls the email one-time-password challenge that runs on every login.
    | All values can be overridden from the environment file.
    */

    // Master switch. When false, login completes straight after the password
    // check and no code is sent. Accepts true/false/1/0/yes/no/on/off.
    'enabled'      => filter_var(env('OTP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // Number of digits in the code.
    'length'       => (int) env('OTP_LENGTH', 6),

    // How long a code stays valid, in minutes.
    'expires_in'   => (int) env('OTP_EXPIRES_IN', 5),

    // Wrong attempts allowed before the code is voided and the user must sign in again.
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Seconds the user must wait before requesting a new code.
    'resend_after' => (int) env('OTP_RESEND_AFTER', 60),

];
