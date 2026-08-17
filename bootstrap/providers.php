<?php

use App\Providers\AppServiceProvider;
use App\Providers\RateLimitServiceProvider;
use App\Providers\RuntimeConfigServiceProvider;

return [
    AppServiceProvider::class,
    RateLimitServiceProvider::class,

    /*
    | PHASE 17. Registered LAST so its boot() runs after config is fully
    | resolved — it overwrites config values that config/mail.php has already
    | read from .env, and doing that before they are loaded would achieve
    | nothing.
    */
    RuntimeConfigServiceProvider::class,
];
