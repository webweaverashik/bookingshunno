<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
|--------------------------------------------------------------------------
| PHASE 17 — the payment deadline reminder
|--------------------------------------------------------------------------
| Half of what the Phase 12 note above anticipated. The reminder is here; the
| expiry is NOT, and deliberately so — see the header of RemindPaymentDeadlines
| for why "what happens when a deadline passes" is still the client's decision
| rather than a default this file quietly picked.
|
| HOURLY, not daily. A deadline can fall at any hour, so a daily run would send
| some people a reminder twenty-three hours earlier than intended and others one
| an hour before the link closed. The command deduplicates against the
| communications log, so running it twelve times between two deadlines still
| produces exactly one email per payment request.
|
| withoutOverlapping() because it shares the minute with queue:work and, on a
| shared host under load, a run can take longer than the gap to the next one.
| runInBackground() so a slow SMTP handshake cannot hold up the scheduler and
| stall the queue behind it.
|
| onFailure logs rather than notifies: there is nobody on call at a studio this
| size, and a failed reminder is a thing to find in the log on Monday, not an
| alert at 2am.
*/
Schedule::command('shunno:remind-payments')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('shunno:remind-payments failed. Payment reminders may not have gone out.');
    });
