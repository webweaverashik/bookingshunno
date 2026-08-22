<?php

namespace App\Console\Commands;

use App\Models\Payment\Payment;
use App\Services\Payment\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The other half of the payment deadline.
 *
 * ---------------------------------------------------------------------------
 * THE DECISION THIS IMPLEMENTS
 * ---------------------------------------------------------------------------
 * RemindPaymentDeadlines has carried an open question since Phase 12: what
 * happens when a deadline passes. It listed four defensible answers and refused
 * to pick one, because two of them move money and give away capacity.
 *
 * The client has now decided: THE RESERVATION IS CANCELLED, and once cancelled
 * nobody may edit it. That is answer three. This command is it.
 *
 * The "nobody may edit it" half needed no code. Cancelled is already terminal —
 * ReservationStatus::closed() contains it, allowedNext() returns an empty array
 * from it, and Reservation::isEditable() is the negation of isClosed(). So an
 * auto-cancelled reservation is locked to Admin and Manager alike by the same
 * rule that locks a manually cancelled one, with no second code path to keep in
 * step. Worth stating because the requirement sounds like it needs enforcing
 * and does not.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT REFUSES TO DO, STILL
 * ---------------------------------------------------------------------------
 * PART-PAID REQUESTS ARE NOT EXPIRED. If somebody has handed over half the
 * money and then missed the deadline for the rest, cancelling their booking
 * leaves the studio holding cash against a reservation that no longer exists —
 * and a receipt pointing at nothing. Whether that is refunded, held as credit,
 * or the deadline simply extended is a decision with a person's name on it.
 *
 * Those are listed at the end of every run and left exactly where they were,
 * visible under the payments register's Overdue filter. If the studio wants a
 * rule for them too, it is one more branch here and a decision from them first.
 *
 * ---------------------------------------------------------------------------
 * THE GRACE PERIOD IS NOT PADDING
 * ---------------------------------------------------------------------------
 * Cancelling the instant due_at passes would race the gateway. SSLCommerz
 * confirms through a browser redirect AND an IPN, and the IPN can lag by
 * minutes; a bank transfer recorded at the counter lags by however long it
 * takes somebody to type it in. A visitor who paid at 23:58 against a midnight
 * deadline must not find their booking cancelled because the callback arrived
 * at 00:01.
 *
 * Two hours by default. The cost of waiting is a slot held slightly longer; the
 * cost of not waiting is cancelling a booking somebody has already paid for,
 * which is not a mistake this system gets to make.
 *
 * ---------------------------------------------------------------------------
 * THE FIRST RUN
 * ---------------------------------------------------------------------------
 * --max exists for one specific hazard: this ships onto a live site that has
 * been running without it, so there is a standing backlog of requests whose
 * deadlines passed weeks ago and which staff have been chasing by hand. A
 * scheduler that quietly cancels thirty bookings on its first tick is not the
 * introduction anybody wants. Above the limit the run stops, changes nothing
 * and says so; --dry-run shows the whole list either way.
 *
 * The same guard covers the scheduler waking after a long outage.
 */
class ExpirePaymentDeadlines extends Command
{
    protected $signature = 'shunno:expire-payments
                            {--dry-run : List what would be cancelled without cancelling or emailing anything}
                            {--grace= : Hours to wait after the deadline before cancelling}
                            {--max=25 : Refuse the run if more than this many would be cancelled at once. 0 disables}';

    protected $description = 'Cancel reservations whose payment deadline passed without payment.';

    public function handle(PaymentService $payments): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $grace  = (int) ($this->option('grace') ?? config('shunno.payments.expiry_grace_hours', 2));
        $max    = (int) $this->option('max');

        $cutoff = CarbonImmutable::now()->subHours(max(0, $grace));

        $due = $this->expirable($cutoff);
        $held = $this->partPaid($cutoff);

        if ($due->isEmpty()) {
            $this->info('No payment deadlines have passed unpaid.');
            $this->reportHeld($held);

            return self::SUCCESS;
        }

        /*
         | Refused rather than truncated.
         |
         | Cancelling the first twenty-five and leaving the rest would make the
         | outcome depend on the order of a database query, which is not a thing
         | anybody should have to reason about afterwards. All or none.
         */
        if ($max > 0 && $due->count() > $max) {
            $this->error(sprintf(
                '%d payment requests are past their deadline — more than the limit of %d. Nothing has been cancelled.',
                $due->count(),
                $max,
            ));
            $this->line('Review them under Payments → Overdue, then re-run with --max=0 once you are satisfied.');

            Log::warning('shunno:expire-payments refused a run above its limit.', [
                'due' => $due->count(),
                'max' => $max,
            ]);

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%d payment %s passed their deadline more than %d %s ago.',
            $due->count(),
            str('request')->plural($due->count()),
            $grace,
            str('hour')->plural($grace),
        ));
        $this->newLine();

        $cancelled = 0;

        foreach ($due as $payment) {
            $label = sprintf(
                '%s  %s  BDT %s  due %s',
                str_pad($payment->reference, 16),
                str_pad($payment->reservation?->reference_code ?? '—', 16),
                number_format((float) $payment->amount_due),
                $payment->due_at?->format('j M Y, g:ia') ?? '—',
            );

            if ($dryRun) {
                $this->line("  would  {$label}");
                $cancelled++;

                continue;
            }

            try {
                /*
                 | The service re-reads under a row lock and re-checks every
                 | condition this query filtered on. Between the query and this
                 | line somebody may have taken a counter payment, and the lock
                 | is the only thing that makes "unpaid" still true when the
                 | write happens.
                 */
                $payments->expire($payment, $grace);
            } catch (RuntimeException $e) {
                $this->warn("  skip   {$label}  — {$e->getMessage()}");

                continue;
            }

            $this->info("  cancel {$label}");
            $cancelled++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->line("Dry run. {$cancelled} reservations would be cancelled and their visitors emailed.");
            $this->reportHeld($held);

            return self::SUCCESS;
        }

        $this->line("Cancelled {$cancelled} reservations. Emails are queued — the worker sends them.");
        $this->reportHeld($held);

        Log::info('shunno:expire-payments completed.', [
            'cancelled' => $cancelled,
            'part_paid_skipped' => $held->count(),
        ]);

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Open, past the deadline plus grace, and nothing received.
     *
     * amount_paid is compared as a number rather than through isPartiallyPaid()
     * so the filter happens in MySQL. The set this sweeps is small, but a
     * command that loads every open payment to reject most of them in PHP is
     * the kind of thing that is fine until it is not.
     *
     * @return Collection<int,Payment>
     */
    private function expirable(CarbonImmutable $cutoff): Collection
    {
        return Payment::query()
            ->open()
            ->where('due_at', '<', $cutoff)
            ->where('amount_paid', '<=', 0)
            ->whereHas('reservation')
            ->with(['reservation.user'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Overdue, but money has arrived. Reported, never touched.
     *
     * @return Collection<int,Payment>
     */
    private function partPaid(CarbonImmutable $cutoff): Collection
    {
        return Payment::query()
            ->open()
            ->where('due_at', '<', $cutoff)
            ->where('amount_paid', '>', 0)
            ->with(['reservation.user'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * @param  Collection<int,Payment>  $held
     */
    private function reportHeld(Collection $held): void
    {
        if ($held->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf(
            '%d overdue %s already had money against them and were left alone:',
            $held->count(),
            str('request')->plural($held->count()),
        ));

        foreach ($held as $payment) {
            $this->line(sprintf(
                '  %s  %s  BDT %s of %s received',
                str_pad($payment->reference, 16),
                str_pad($payment->reservation?->reference_code ?? '—', 16),
                number_format((float) $payment->amount_paid),
                number_format((float) $payment->amount_due),
            ));
        }

        $this->line('  These need a refund or an extension decided by a person.');
    }
}
