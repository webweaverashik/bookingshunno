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
| --stop-when-empty IS GONE, and that is the whole point of this block.
|
| It exited the moment the queue drained, which is almost immediately — so the
| worker was alive for a fraction of a second per minute and a job pushed just
| after it quit waited for the next tick. That is why one approval email took
| up to a minute to arrive with nothing whatsoever queued behind it: the delay
| was never a backlog, it was a worker that had already gone home. Average wait
| about thirty seconds, worst case sixty.
|
| Without it the worker LISTENS for its fifty-five seconds and picks a job up
| within about one. Cron starts a fresh one each minute, so there is a gap of
| roughly five seconds a minute where a job waits — the price of not having a
| supervisor on shared hosting, and two orders of magnitude better than before.
|
|   --max-time=55   exits before the next tick, so runs never stack up. Shared
|                   hosts kill long processes anyway, and this leaves before
|                   being killed rather than after.
|
|   --sleep=1       how long to wait after finding nothing. The default is 3,
|                   which would put three seconds back onto every email for no
|                   reason: one idle query a second against a database this
|                   size costs nothing measurable.
|
|   --tries=3       a Gmail SMTP hiccup retries rather than landing in
|                   failed_jobs on the first stumble.
|
| ONE THING WE GIVE UP: --stop-when-empty also meant every run booted the
| current code, which is how a deploy took effect here without queue:restart —
| unavailable on this host. A worker started before an upload now runs the old
| code until its fifty-five seconds are up. Under a minute, once per deploy,
| and worth it.
|
| withoutOverlapping() is belt and braces on top of --max-time. It takes a
| cache lock; the cache driver is `database`, so this works without Redis.
*/
Schedule::command('queue:work --max-time=55 --sleep=1 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();

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
| The payment deadline reminder
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

/*
|--------------------------------------------------------------------------
| The payment deadline expiry
|--------------------------------------------------------------------------
| The half the reminder block above said was still the client's decision. It
| has been made: a deadline that passes unpaid cancels the reservation.
|
| HOURLY, matching the reminder, and for the same reason — a deadline can fall
| at any hour. The command applies its own grace period on top, so running it
| twelve times inside one grace window cancels nothing early.
|
| --max defaults to 25 and the run REFUSES above it rather than truncating.
| That is what stops a scheduler waking after an outage from cancelling a
| month of bookings in one tick.
*/
Schedule::command('shunno:expire-payments')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        Log::error('shunno:expire-payments failed. Overdue reservations may not have been cancelled.');
    });
