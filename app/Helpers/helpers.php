<?php

use Illuminate\Support\Facades\Artisan;

if (! function_exists('clearServerCache')) {
    /**
     * Clear every cache Laravel keeps, plus the application cache store.
     *
     * optimize:clear runs cache:clear, config:clear, event:clear, route:clear,
     * view:clear and compiled:clear in one call. cache:clear matters as much as
     * the rest of them here: settings, the sidebar menu and the workshop list
     * are all memoised in the cache store, so "I changed a setting and the site
     * still shows the old one" is the thing this button actually fixes.
     */
    function clearServerCache(): void
    {
        Artisan::call('optimize:clear');
    }
}
