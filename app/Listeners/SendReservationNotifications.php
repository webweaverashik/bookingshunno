<?php

namespace App\Listeners;

use App\Enums\ReservationMailKind;
use App\Events\PaymentReceived;
use App\Events\PaymentRequested;
use App\Events\ReservationRequested;
use App\Events\VoucherIssued;
use App\Events\ReservationStatusChanged;
use App\Models\Auth\User;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Reservation;
use App\Services\CommunicationLogger;
use App\Services\SettingsRepository;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 11 — turns reservation events into email.
 *
 * Deliberately NOT ShouldQueue itself. Everything it does is decide who gets
 * what and hand a queued Mailable to the queue, which is cheap; queueing the
 * listener as well would put a job on the queue whose only purpose is to put
 * another job on the queue.
 *
 * Every send is wrapped. A mail failure must never propagate back into the
 * request that caused it: an approval that is already in the database has
 * happened, and turning it into a 500 would leave the admin thinking it had
 * not. Failures are logged loudly enough to find.
 *
 * REGISTRATION: Laravel discovers listeners in app/Listeners automatically by
 * the event type-hinted on their handle methods. If event discovery is ever
 * turned off, register these two explicitly in a service provider:
 *
 *     Event::listen(ReservationRequested::class, [SendReservationNotifications::class, 'handleRequested']);
 *     Event::listen(ReservationStatusChanged::class, [SendReservationNotifications::class, 'handleStatusChanged']);
 */
class SendReservationNotifications
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CommunicationLogger $log,
    ) {
    }

    public function handleRequested(ReservationRequested $event): void
    {
        $this->sendToVisitor($event->reservation, ReservationMailKind::Received);
    }

    public function handleStatusChanged(ReservationStatusChanged $event): void
    {
        $kind = ReservationMailKind::forStatus($event->to);

        if (! $kind) {
            return;
        }

        // Returning a request to the review queue also lands on a status, but
        // it is an internal tidy-up and the visitor has no reason to hear about
        // it. forStatus() already returns null for Pending; this guard exists
        // for the case a later phase gives Pending a template and forgets.
        if ($kind->isInternal()) {
            $this->sendToStaff($event->reservation, $kind, $event->note);

            return;
        }

        $this->sendToVisitor($event->reservation, $kind, $event->note);
    }

    /**
     * PHASE 12C — the payment link.
     *
     * Raised by PaymentService rather than derived from the status change,
     * because the email needs the amount and the deadline. The corresponding
     * status, PaymentRequested, is mapped to null in
     * ReservationMailKind::forStatus() precisely so this does not double up.
     */
    public function handlePaymentRequested(PaymentRequested $event): void
    {
        $this->sendToVisitor(
            $event->payment->reservation,
            ReservationMailKind::PaymentRequested,
            $event->payment->note,
            $event->payment,
        );
    }

    /**
     * PHASE 12C — the receipt.
     *
     * Sent for every settlement, whether the gateway confirmed it or a member
     * of staff wrote it down. A visitor who paid cash at the counter should
     * still get their payslip by email.
     */
    public function handlePaymentReceived(PaymentReceived $event): void
    {
        $this->sendToVisitor(
            $event->payment->reservation,
            ReservationMailKind::PaymentReceived,
            $event->transaction->note,
            $event->payment,
            $event->transaction,
        );
    }

    /**
     * PHASE 14A — a voucher has been issued.
     *
     * Handled here rather than in a listener of its own because everything that
     * makes this awkward — the notifications kill switch, the failure handling,
     * the communications log — is already solved in this class, and a second
     * listener would either duplicate it or quietly skip it.
     */
    public function handleVoucherIssued(VoucherIssued $event): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->log->sendVoucher($event->voucher);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function sendToVisitor(
        ?Reservation $reservation,
        ReservationMailKind $kind,
        ?string $note = null,
        ?Payment $payment = null,
        ?PaymentTransaction $transaction = null,
    ): void {
        // Nullable now that payments can raise this. A payment whose
        // reservation was hard-deleted has nobody to write to, and the cascade
        // means that should be impossible — but a null here would otherwise be
        // a fatal in a queued job rather than a line in the log.
        if (! $reservation) {
            return;
        }

        if (! $this->enabled()) {
            return;
        }

        $email = $reservation->user?->email;

        if (! $email) {
            Log::warning('Reservation notification skipped: no visitor email.', [
                'reservation' => $reservation->reference_code,
                'kind'        => $kind->value,
            ]);

            return;
        }

        $this->dispatch($email, $reservation, $kind, $note, $payment, $transaction);
    }

    /**
     * Internal notifications go to every Admin.
     *
     * No assignee, because an escalation is not addressed to a person — the
     * client's rule is that an Admin decides, not that a particular Admin does.
     * Inactive accounts are excluded so a departed owner's address does not
     * keep receiving studio business.
     */
    private function sendToStaff(Reservation $reservation, ReservationMailKind $kind, ?string $note = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $recipients = User::query()
            // 'Admin' as a literal, matching User::isAdmin() and the role
            // middleware. There is no ROLE_ADMIN constant on User — only
            // ROLE_VISITOR — and inventing one here would leave two spellings
            // of the same role in the codebase.
            ->role('Admin')
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->all();

        if ($recipients === []) {
            Log::warning('Escalation notification had no active Admin to send to.', [
                'reservation' => $reservation->reference_code,
            ]);

            return;
        }

        $this->dispatch($recipients, $reservation, $kind, $note);
    }

    /**
     * @param  string|array<int,string>  $to
     */
    private function dispatch(
        string|array $to,
        Reservation $reservation,
        ReservationMailKind $kind,
        ?string $note,
        ?Payment $payment = null,
        ?PaymentTransaction $transaction = null,
    ): void {
        /*
         | PHASE 13B — handed to CommunicationLogger rather than to Mail
         | directly. It writes the log row first so the id can be stamped into
         | the message, and it owns the try/catch that used to live here. One
         | class sends every reservation email, first time and resend alike, so
         | the two cannot drift.
         */
        $this->log->send($to, $reservation, $kind, $note, $payment, $transaction);
    }

    /**
     * A single switch that silences every outbound notification.
     *
     * Exists for two real situations: restoring a production database into
     * staging, where sending is actively harmful, and the first days of go-live
     * when the client may want the workflow running before the wording is
     * signed off.
     */
    private function enabled(): bool
    {
        return (bool) $this->settings->get('notifications.enabled', true);
    }
}
