<?php

namespace App\Listeners\Communication;

use App\Enums\Communication\ReservationMailKind;
use App\Enums\Payment\PaymentStatus;
use App\Events\Payment\PaymentReceived;
use App\Events\Payment\PaymentRequested;
use App\Events\Reservation\ReservationRequested;
use App\Events\Voucher\VoucherIssued;
use App\Events\Reservation\ReservationStatusChanged;
use App\Models\Auth\User;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use App\Models\Reservation\Reservation;
use App\Services\Communication\CommunicationLogger;
use App\Services\Setting\SettingsRepository;
use Illuminate\Support\Facades\Log;

/**
 * Turns reservation events into email.
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
 * turned off, register these explicitly in a service provider:
 *
 *     Event::listen(ReservationRequested::class, [SendReservationNotifications::class, 'handleRequested']);
 *     Event::listen(ReservationStatusChanged::class, [SendReservationNotifications::class, 'handleStatusChanged']);
 *     Event::listen(PaymentRequested::class, [SendReservationNotifications::class, 'handlePaymentRequested']);
 *     Event::listen(PaymentReceived::class, [SendReservationNotifications::class, 'handlePaymentReceived']);
 *     Event::listen(VoucherIssued::class, [SendReservationNotifications::class, 'handleVoucherIssued']);
 */
class SendReservationNotifications
{
    /**
     * Who counts as staff, for the alerts that go to "everyone".
     *
     * Literals, matching User::isAdmin()/isManager() and the role middleware.
     * There is no ROLE_ADMIN constant on User — only ROLE_VISITOR — and
     * inventing one here would leave two spellings of the same role in the
     * codebase.
     *
     * @var array<int,string>
     */
    private const STAFF = ['Admin', 'Manager'];

    /**
     * Admin only, for anything a Manager cannot act on.
     *
     * @var array<int,string>
     */
    private const ADMINS = ['Admin'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly CommunicationLogger $log,
    ) {
    }

    /**
     * A request has arrived.
     *
     * TWO EMAILS, one event. The visitor is told we have it; the studio is told
     * there is something to look at. They are separate kinds rather than one
     * message with two recipients because the wording has to differ — the
     * internal one carries the visitor's phone number and a link into the admin
     * panel, neither of which belongs in the visitor's copy.
     *
     * The staff alert goes to Admin AND Manager, per the client. A Manager
     * cannot approve, but they can read, chase and escalate, and the studio's
     * answer to "who should know a booking came in" was everyone.
     */
    public function handleRequested(ReservationRequested $event): void
    {
        $this->sendToVisitor($event->reservation, ReservationMailKind::Received);

        $this->sendToStaff($event->reservation, ReservationMailKind::NewRequest, null, self::STAFF);
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
            $this->sendToStaff($event->reservation, $kind, $event->note, self::ADMINS);

            return;
        }

        // Completed arrives here as of Phase 36 and is visitor-facing, so it
        // needs nothing special — it falls through the same path as an approval
        // or a decline.
        $this->sendToVisitor($event->reservation, $kind, $event->note);
    }

    /**
     * The payment link.
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
     * The receipt, and — once the request is settled — the studio's copy.
     *
     * The visitor's receipt goes out for every settlement, whether the gateway
     * confirmed it or a member of staff wrote it down. Somebody who paid cash
     * at the counter should still get their payslip by email.
     *
     * THE STAFF COPY IS DIFFERENT: it goes out only when the request is fully
     * paid. This event also fires for a part payment, and an alert on every
     * instalment would mean two emails for one booking taken in halves, which
     * is exactly the noise the client is trying to avoid. Admin only, per the
     * client's wording, and because there is nothing here for a Manager to do.
     *
     * Gated on the PAYMENT being paid rather than on the reservation being
     * Confirmed. Those are the same thing in the ordinary case, and where they
     * differ — money landing against a booking cancelled while the visitor was
     * at the gateway — the difference is the single most important thing an
     * Admin could be told. The template prints the reservation's actual status
     * so that case announces itself instead of being filtered out here.
     */
    public function handlePaymentReceived(PaymentReceived $event): void
    {
        $reservation = $event->payment->reservation;

        $this->sendToVisitor(
            $reservation,
            ReservationMailKind::PaymentReceived,
            $event->transaction->note,
            $event->payment,
            $event->transaction,
        );

        if ($event->payment->status !== PaymentStatus::Paid || ! $reservation) {
            return;
        }

        /*
         | Re-read rather than trusted.
         |
         | applySettlement() transitions the reservation through a SEPARATE
         | instance loaded under a row lock, so the one hanging off this payment
         | may still be carrying the status it had before the money arrived.
         | The staff email exists to state where the booking now stands; reading
         | it from a stale relation would make it state the opposite.
         */
        $this->sendToStaff(
            $reservation->fresh() ?? $reservation,
            ReservationMailKind::PaymentSettled,
            $event->transaction->note,
            self::ADMINS,
            $event->payment,
            $event->transaction,
        );
    }

    /**
     * A voucher has been issued.
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
     * Internal notifications, to whichever roles the caller names.
     *
     * The role list is a parameter rather than a constant inside this method
     * because the three internal kinds genuinely differ in audience: a new
     * request is everyone's business, an escalation and a settled payment are
     * an Admin's. Hard-coding one answer here is what left Managers receiving
     * nothing at all.
     *
     * No assignee, in any case. The client's rule is that an Admin decides, not
     * that a particular Admin does. Inactive accounts are excluded so a
     * departed owner's address does not keep receiving studio business.
     *
     * @param  array<int,string>  $roles
     */
    private function sendToStaff(
        Reservation $reservation,
        ReservationMailKind $kind,
        ?string $note = null,
        array $roles = self::ADMINS,
        ?Payment $payment = null,
        ?PaymentTransaction $transaction = null,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $recipients = User::query()
            ->role($roles)
            ->where('is_active', true)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            Log::warning('Internal notification had nobody to send to.', [
                'reservation' => $reservation->reference_code,
                'kind'        => $kind->value,
                'roles'       => $roles,
            ]);

            return;
        }

        $this->dispatch($recipients, $reservation, $kind, $note, $payment, $transaction);
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
         | Handed to CommunicationLogger rather than to Mail
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
