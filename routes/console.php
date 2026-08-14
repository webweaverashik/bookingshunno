<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| The live server is Webuzo shared hosting with no SSH, so everything here
| depends on ONE cron entry in the panel:
|
|     * * * * * /path/to/php /home/USER/booking/artisan schedule:run
|
| See DEPLOYMENT-WEBUZO.md. If that entry is missing or its PHP path is wrong,
| nothing below ever runs and the only symptom is silence — which is why the
| deployment notes say to verify it rather than assume it.
*/

/*
| The queue, driven by the scheduler.
|
| A second cron entry running queue:work directly would also work and is
| marginally more robust — mail would survive the scheduler breaking. It lives
| here instead because one cron entry is one thing to get right, one thing to
| find again in a year, and one thing to fix when the PHP path changes after a
| panel upgrade.
|
|   --stop-when-empty  exits the moment the queue drains, so an idle minute
|                      costs a process start and nothing else. It also means
|                      each run boots the current code, which is how a deploy
|                      takes effect without queue:restart — unavailable here.
|
|   --max-time=50      exits before the next tick, so runs never stack up.
|                      Shared hosts kill long processes anyway.
|
|   --tries=3          a Gmail SMTP hiccup retries rather than landing in
|                      failed_jobs on the first stumble.
|
| withoutOverlapping() is belt and braces on top of --max-time. It takes a
| cache lock; the cache driver is `database`, so this works without Redis.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
| Housekeeping. failed_jobs grows forever otherwise, and on shared hosting
| nobody is watching it.
|
| 14 days is long enough that a failure noticed on a Monday is still there the
| following Monday — which is the realistic support window for a studio this
| size.
*/
Schedule::command('queue:prune-failed --hours=336')->weekly();

/*
| PHASE 12 will add here:
|   - expiring payment requests past payment_deadline_hours
|   - a reminder before that deadline
| Both are scheduled work, and both are why this file is set up now rather than
| when they are written.
*/
