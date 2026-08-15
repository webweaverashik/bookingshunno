<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSLCommerz (Phase 13)
    |--------------------------------------------------------------------------
    | Credentials live in .env and ONLY in .env. Not in the settings table, not
    | in the admin panel. Database rows travel in ways .env does not — nightly
    | backups, a staging refresh, a dump on somebody's laptop, and eventually a
    | CSV export — and anyone with admin access is a much larger group than
    | anyone who can deploy. §11 of the brief requires this.
    |
    | SANDBOX FOLLOWS THE ENVIRONMENT, not a toggle in the panel. A switch
    | someone can flip in two clicks means production can be put into sandbox
    | mode, and the symptom is payments that look successful and never settle —
    | discovered at month end rather than at the time. Defaults to true so a
    | missing .env key cannot accidentally transact for real.
    |
    | The operational switch — whether online payment is offered at all — is a
    | separate thing and DOES live in settings, as payments.online_enabled, so
    | the studio can turn it off during a gateway outage without a deploy.
    */
    'sslcommerz' => [
        'store_id'       => env('SSLCZ_STORE_ID'),
        'store_password' => env('SSLCZ_STORE_PASSWORD'),
        'sandbox'        => env('SSLCZ_SANDBOX', true),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
