<?php

namespace App\Console\Commands;

use App\Enums\Communication\ReservationMailKind;
use App\Models\Communication\Communication;
use App\Models\Payment\Payment;
use App\Services\Communication\CommunicationLogger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * PHASE 17 — the payment deadline, half resolved.
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS DOES, AND WHAT IT REFUSES TO DO
 * ---------------------------------------------------------------------------
 * "What happens when a payment deadline passes" has been an open question since
 * Phase 12, and it is still open, because it is not a technical question. There
 * are at least four defensible answers:
 *
 *   nothing — the request stays open and staff chase it by hand
 *   the request expires but the reservation is held
 *   the reservation is cancelled and the slot released
 *   the reservation is cancelled and any part-payment refunded
 *
 * The last two move money and give away capacity, and §25 of the brief is
 * explicit that ambiguities touching either are identified rather than invented.
 * A studio that quietly cancelled a booking somebody had already part-paid for
 * would have a very bad conversation on its hands, and no line of code here can
 * make that the client's decision after the fact.
 *
 * So this command implements only the half that is unambiguously wanted and
 * cannot be got wrong: IT REMINDS PEOPLE BEFORE THE DEADLINE. It changes no
 * status, releases no slot, touches no money. A reservation whose deadline
 * passes is exactly where it was — visible in the payments register under the
 * existing "Overdue" filter, waiting for a person.
 *
 * When the client decides, the missing half is a second command beside this one
 * and a status transition that already exists. Nothing here has to be undone.
 *
 * ---------------------------------------------------------------------------
 * SENDING IT ONCE
 * ---------------------------------------------------------------------------
 * The scheduler runs every hour, so the obvious hazard is reminding the same
 * person every hour until they pay or the deadline passes — which would be
 * worse than not reminding them at all.
 *
 * Deduplicated against the `communications` table rather than a new column on
 * payments. That table already records every message this application has tried
 * to send, indexed on (payment_id, created_at), and it is the evidence staff
 * read in the reservation drawer. A separate `reminder_sent_at` flag would be a
 * second source of truth about the same fact, and the two would eventually
 * disagree — at which point the flag says a reminder went out and the history
 * shows it never did, and nobody can tell which is lying.
 */
class RemindPaymentDeadlines extends Command
{
    protected $signature = 'shunno:remind-payments
                            {--hours= : Send to requests falling due within this many hours}
                            {--dry-run : List who would be emailed without sending anything}';

    protected $description = 'Remind visitors whose payment deadline is approaching. Sends once per payment request.';

    public function handle(CommunicationLogger $logger): int
    {
        $window = (int) ($this->option('hours') ?: config('shunno.payments.reminder_hours', 24));
        $dryRun = (bool) $this->option('dry-run');

        $due = $this->approaching($window);

        if ($due->isEmpty()) {
            $this->info("No payment requests fall due in the next {$window} hours.");

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($due as $payment) {
            $reservation = $payment->reservation;

            /*
             | Guards, in the order they are most likely to bite.
             |
             | A payment whose reservation has been soft-deleted, or whose
             | visitor account is gone, has nowhere to send to. Neither should
             | happen; both would be an unhandled null in a scheduled job that
             | nobody is watching, which is the failure mode this whole phase is
             | about.
             */
            if (! $reservation || ! $reservation->user?->email) {
                $this->warn("Skipped {$payment->reference}: no visitor address.");

                continue;
            }

            if ($this->alreadyReminded($payment)) {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '  would remind %s <%s> — %s, due %s',
                    $reservation->user->name,
                    $reservation->user->email,
                    $payment->reference,
                    $payment->due_at->format('j M, g:i A'),
                ));
                $sent++;

                continue;
            }

            /*
             | Through CommunicationLogger, not Mail::to() directly. That is what
             | writes the row this command reads back on its next run, so the
             | send and the record of the send cannot come apart — and it is
             | what puts the reminder in the drawer where staff will look for it
             | when a visitor says nobody told them.
             |
             | triggeredBy is null on purpose: no member of staff did this, and
             | attributing it to one would misread the history.
             */
            $logger->send(
                to: $reservation->user->email,
                reservation: $reservation,
                kind: ReservationMailKind::PaymentReminder,
                payment: $payment,
            );

            $sent++;
        }

        $this->info($dryRun
            ? "Dry run: {$sent} reminder(s) would be sent."
            : "Sent {$sent} reminder(s)."
        );

        return self::SUCCESS;
    }

    /**
     * Open requests falling due inside the window.
     *
     * `open()` excludes anything already paid or cancelled. The lower bound is
     * now() rather than nothing, so a request whose deadline has ALREADY passed
     * is not reminded about — telling somebody to hurry up and meet a deadline
     * that went by yesterday is worse than silence, and that case belongs to
     * whatever the client eventually decides expiry should do.
     *
     * @return Collection<int,Payment>
     */
    private function approaching(int $hours): Collection
    {
        return Payment::query()
            ->open()
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addHours($hours)])
            ->with(['reservation.user', 'reservation.items.workshop'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Has a reminder already gone out for this request?
     *
     * Deliberately does NOT check whether it was delivered. A message that was
     * queued and then bounced is still a message this system decided to send,
     * and re-sending it on the next tick would loop hourly against a dead
     * mailbox. A genuine failure is visible in the communications log with its
     * error, and a person can resend it from the drawer.
     */
    private function alreadyReminded(Payment $payment): bool
    {
        return Communication::where('payment_id', $payment->id)
            ->where('kind', ReservationMailKind::PaymentReminder->value)
            ->exists();
    }
}
