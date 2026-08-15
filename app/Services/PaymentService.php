<?php

namespace App\Services;

use App\Enums\PaymentChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ReservationStatus;
use App\Models\Auth\User;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PHASE 12A — the single place a payment request is created or settled.
 *
 * The controller does not do arithmetic and neither does the browser. Both ask
 * this class, so the figure in the modal, the figure in the database and the
 * figure the visitor is eventually shown all come from one calculation.
 *
 * Three operations, and each one moves BOTH a payment and a reservation. That
 * pairing is the whole reason this class exists: a payment marked paid whose
 * reservation is still sitting in "Payment requested" is the kind of split
 * state that is very cheap to create from a controller and very expensive to
 * find later.
 */
class PaymentService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly SettingsRepository $settings,
        private readonly ReservationService $reservations,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    */

    /**
     * The figures for both payment types, without writing anything.
     *
     * Feeds the request modal. The browser receives finished numbers and swaps
     * between them when the type changes; it never multiplies anything itself,
     * so a rounding rule cannot differ between the preview and the record that
     * is eventually written.
     *
     * @return array<string,mixed>
     */
    public function preview(Reservation $reservation): array
    {
        $total = $reservation->payableTotal();

        $booking = $this->pricing->split($total, true);
        $full    = $this->pricing->split($total, false);

        $hours = $this->defaultDeadlineHours();

        return [
            'reservation_total' => $total,
            'default_hours'     => $hours,
            'default_due_at'    => $this->deadlineFrom($hours)->format('D, j M Y g:i A'),
            'has_manual_price'  => $reservation->hasManualPrice(),
            'types'             => [
                PaymentType::BookingFee->value => [
                    'label'      => PaymentType::BookingFee->describe($booking['percentage']),
                    'percentage' => $booking['percentage'],
                    'payable'    => $booking['payable'],
                    'remaining'  => $booking['remaining'],
                ],
                PaymentType::Full->value => [
                    'label'      => PaymentType::Full->describe($full['percentage']),
                    'percentage' => $full['percentage'],
                    'payable'    => $full['payable'],
                    'remaining'  => $full['remaining'],
                ],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Requesting
    |--------------------------------------------------------------------------
    */

    /**
     * Ask the visitor for money and move the reservation to Payment requested.
     *
     * @param  int|null  $deadlineHours  Overrides the configured default for
     *                                   this one request. The studio does agree
     *                                   "pay me by Friday" on the phone, and
     *                                   forcing that into a global setting
     *                                   would change every other request too.
     *
     * @throws RuntimeException when the reservation is not in a state to be
     *                          charged, or already has a live request.
     */
    public function request(
        Reservation $reservation,
        PaymentType $type,
        User $actor,
        ?int $deadlineHours = null,
        ?string $note = null,
    ): Payment {
        return DB::transaction(function () use ($reservation, $type, $actor, $deadlineHours, $note) {
            /*
             | Re-read under a lock before deciding anything.
             |
             | Two admins with the drawer open on the same approved reservation
             | is not hypothetical — it is the normal case when the studio is
             | busy. Without this, both pass the "no open request" check and the
             | visitor gets two payment links for the same visit.
             */
            $reservation = Reservation::whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $reservation->status->canTransitionTo(ReservationStatus::PaymentRequested)) {
                throw new RuntimeException(
                    "A payment cannot be requested for a reservation that is {$reservation->status->label()}."
                );
            }

            if ($reservation->payments()->open()->exists()) {
                throw new RuntimeException(
                    'This reservation already has a payment request awaiting payment. Cancel it before sending another.'
                );
            }

            $total = $reservation->payableTotal();
            $split = $this->pricing->split($total, $type === PaymentType::BookingFee);

            // A zero request would send a visitor a link to pay nothing. That
            // is reachable — an Admin can agree a total of 0 as a courtesy —
            // and the right answer is to approve and confirm it by hand, not to
            // invoice for nothing.
            if ($split['payable'] <= 0) {
                throw new RuntimeException(
                    'This reservation has nothing to pay. Confirm it directly instead of requesting a payment.'
                );
            }

            $hours = $deadlineHours ?: $this->defaultDeadlineHours();

            $payment = new Payment();

            $payment->forceFill([
                'reference'         => $this->generateReference(),
                'token'             => $this->generateToken(),
                'reservation_id'    => $reservation->id,
                'type'              => $type,
                'percentage'        => $split['percentage'],
                'reservation_total' => $total,
                'amount_due'        => $split['payable'],
                'amount_paid'       => 0,
                'status'            => PaymentStatus::Pending,
                'due_at'            => $this->deadlineFrom($hours),
                'note'              => $note,
                'requested_by'      => $actor->id,
            ])->save();

            /*
             | Inside the transaction, deliberately, and safe.
             |
             | Phase 11 raises its notification event from transition() and does
             | so OUTSIDE that method's own transaction — but calling it from in
             | here nests it, so the event fires while this one is still open.
             | That is fine because SendReservationNotifications catches every
             | Throwable itself and only logs; a mail failure cannot reach back
             | and roll this up. And the alternative — committing the payment,
             | then transitioning — risks a payment row whose reservation never
             | moved, which is precisely the split state this class exists to
             | prevent.
             |
             | Nothing is emailed for PaymentRequested yet in any case:
             | ReservationMailKind::forStatus() returns null for it until 12B.
             */
            $this->reservations->transition(
                $reservation,
                ReservationStatus::PaymentRequested,
                $actor,
                sprintf(
                    '%s requested: BDT %s of BDT %s, due %s. Reference %s.%s',
                    $type->describe($split['percentage']),
                    number_format($split['payable']),
                    number_format($total),
                    $payment->due_at->format('j M Y, g:i A'),
                    $payment->reference,
                    $note ? ' ' . $note : '',
                ),
            );

            return $payment->fresh(['reservation.user', 'requestedBy']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Settling
    |--------------------------------------------------------------------------
    */

    /**
     * Record money against an open request.
     *
     * Accepts less than the full amount. The studio genuinely does take a
     * deposit in cash and the balance on the day, and refusing a partial
     * receipt would push staff into recording the wrong figure so the form
     * accepts it. amount_paid accumulates; the request settles only when it is
     * covered.
     *
     * @throws RuntimeException when the request is closed or the amount is not
     *                          collectable.
     */
    public function record(
        Payment $payment,
        float $amount,
        PaymentMethod $method,
        User $actor,
        ?string $reference = null,
        ?CarbonImmutable $paidAt = null,
        ?string $note = null,
    ): Payment {
        return DB::transaction(function () use ($payment, $amount, $method, $actor, $reference, $paidAt, $note) {
            $payment = Payment::whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payment->isOpen()) {
                throw new RuntimeException(
                    "This payment request is {$payment->status->label()} — nothing further can be recorded against it."
                );
            }

            if ($amount <= 0) {
                throw new RuntimeException('The amount received has to be more than zero.');
            }

            // Poisha comparison, not float. 4999.995 rounds to a figure that is
            // "greater than" 4999.99 in floating point often enough to matter
            // when somebody types the outstanding amount exactly.
            $outstandingPoisha = (int) round($payment->outstanding() * 100);
            $amountPoisha      = (int) round($amount * 100);

            if ($amountPoisha > $outstandingPoisha) {
                throw new RuntimeException(sprintf(
                    'That is more than the BDT %s still outstanding on this request.',
                    number_format($payment->outstanding()),
                ));
            }

            $paidPoisha = (int) round((float) $payment->amount_paid * 100) + $amountPoisha;
            $settled    = $paidPoisha >= (int) round((float) $payment->amount_due * 100);
            $balance    = ($outstandingPoisha - $amountPoisha) / 100;

            $payment->forceFill([
                'amount_paid'       => $paidPoisha / 100,
                'method'            => $method,
                'gateway_reference' => $reference,
                'recorded_by'       => $actor->id,
                'status'            => $settled ? PaymentStatus::Paid : PaymentStatus::Pending,
                'paid_at'           => $settled ? ($paidAt ?? CarbonImmutable::now()) : null,
            ])->save();

            /*
             | PHASE 12B — the receipt.
             |
             | Written for EVERY settlement, manual or gateway, because the
             | client requires a payslip in both cases and a payslip needs its
             | own amount, method and moment. The columns stamped on the payment
             | above are now a denormalised copy of this row; before 12B they
             | were the only record, which meant a second part payment silently
             | overwrote the first one's method.
             |
             | balance_after is snapshotted rather than derived. A receipt the
             | visitor is holding must say the same thing next month, and
             | recomputing "still to come" at render time would rewrite it the
             | moment anything else moved.
             */
            $transaction = new PaymentTransaction();

            $transaction->forceFill([
                'reference'          => $this->generateTransactionReference(),
                'payment_id'         => $payment->id,
                'channel'            => PaymentChannel::forMethod($method),
                'method'             => $method,
                'status'             => 'success',
                'amount'             => $amountPoisha / 100,
                'balance_after'      => $balance,
                'external_reference' => $reference,
                'note'               => $note,
                'received_at'        => $paidAt ?? CarbonImmutable::now(),
                'recorded_by'        => $actor->id,
            ])->save();

            $reservation = $payment->reservation()->lockForUpdate()->firstOrFail();

            $line = sprintf(
                'BDT %s received by %s against %s. Receipt %s.%s%s',
                number_format($amount),
                $method->label(),
                $payment->reference,
                $transaction->reference,
                $reference ? " Ref {$reference}." : '',
                $note ? ' ' . $note : '',
            );

            if (! $settled) {
                // Part paid. The reservation stays where it is — it is not
                // confirmed until the amount asked for has actually arrived —
                // and the history says how much is left so nobody has to work
                // it out from two other rows.
                $this->reservations->note(
                    $reservation,
                    $actor,
                    $line . sprintf(' BDT %s still outstanding on this request.', number_format($payment->outstanding())),
                );

                return $payment->fresh(['reservation.user', 'recordedBy', 'transactions']);
            }

            if ($reservation->status->canTransitionTo(ReservationStatus::Confirmed)) {
                $this->reservations->transition(
                    $reservation,
                    ReservationStatus::Confirmed,
                    $actor,
                    $line,
                );
            } else {
                /*
                 | Money arrived against a reservation that is no longer waiting
                 | for it — cancelled while the visitor was at the ATM, most
                 | likely. The receipt is still recorded, because it happened
                 | and pretending otherwise loses real money from the books, but
                 | the status is left alone and the history says so plainly.
                 | Somebody has a refund to process and this is how they find
                 | out.
                 */
                $this->reservations->note(
                    $reservation,
                    $actor,
                    $line . " The reservation is {$reservation->status->label()}, so it has not been confirmed. This may need refunding.",
                );
            }

            return $payment->fresh(['reservation.user', 'recordedBy', 'transactions']);
        });
    }

    /**
     * Withdraw a request that should not have gone out, or that has lapsed.
     *
     * Puts the reservation back to Approved rather than leaving it stranded in
     * Payment requested, so it reappears where staff expect it and a fresh
     * request can be sent.
     *
     * Refuses once money has been taken. A part-paid request that simply
     * vanished would leave a receipt pointing at nothing; whoever wants that
     * money back has a refund to process first, and that is a decision with a
     * person's name on it rather than a side effect of a Cancel button.
     */
    public function cancel(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason) {
            $payment = Payment::whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payment->isOpen()) {
                throw new RuntimeException(
                    "This payment request is already {$payment->status->label()}."
                );
            }

            if ((float) $payment->amount_paid > 0) {
                throw new RuntimeException(sprintf(
                    'BDT %s has already been received against this request. Refund it before cancelling.',
                    number_format((float) $payment->amount_paid),
                ));
            }

            $payment->forceFill([
                'status'              => PaymentStatus::Cancelled,
                'cancellation_reason' => $reason,
            ])->save();

            $reservation = $payment->reservation()->lockForUpdate()->firstOrFail();

            $line = "Payment request {$payment->reference} cancelled. {$reason}";

            if ($reservation->status->canTransitionTo(ReservationStatus::Approved)) {
                $this->reservations->transition(
                    $reservation,
                    ReservationStatus::Approved,
                    $actor,
                    $line,
                );
            } else {
                $this->reservations->note($reservation, $actor, $line);
            }

            return $payment->fresh(['reservation.user']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function defaultDeadlineHours(): int
    {
        return (int) $this->settings->get(
            'payment_deadline_hours',
            config('shunno.payment_deadline_hours'),
        ) ?: 48;
    }

    /**
     * The deadline, rounded to a civil hour.
     *
     * Raw arithmetic gives "pay by 2:39 AM on Sunday", which is mechanically
     * correct and reads as nonsense to a visitor — and worse, expires while
     * they are asleep and the studio is shut. So the deadline lands at the END
     * of the day it falls on, at the hour the cafe closes, which is both a
     * sensible thing to print and slightly generous. Generous is the right
     * direction to err: the cost of an extra few hours is a slot held a little
     * longer, and the cost of being mean is a visitor who missed it by minutes.
     *
     * Short deadlines are left alone. Under twelve hours the studio is agreeing
     * something specific on the phone — "pay within two hours or I release it"
     * — and rounding that out to closing time would defeat the point.
     */
    private function deadlineFrom(int $hours): CarbonImmutable
    {
        $due = CarbonImmutable::now()->addHours(max($hours, 1));

        if ($hours < 12) {
            return $due;
        }

        $closing = (string) config('shunno.operating.cafe_end', '23:00');

        return $due->setTimeFromTimeString($closing);
    }

    /** PAY-2608-K4RT — the payment request. */
    private function generateReference(): string
    {
        return $this->uniqueCode('PAY', fn (string $code) => Payment::where('reference', $code)->exists());
    }

    /** RCP-2608-K4RT — one receipt. Printed at the top of the payslip. */
    private function generateTransactionReference(): string
    {
        return $this->uniqueCode('RCP', fn (string $code) => PaymentTransaction::where('reference', $code)->exists());
    }

    /**
     * Same alphabet as the reservation code — no I, O, 0 or 1 — because all
     * three of these get read out over the phone.
     *
     * @param  callable(string):bool  $taken
     */
    private function uniqueCode(string $prefix, callable $taken): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = $prefix . '-' . now()->format('ym') . '-' . $suffix;
        } while ($taken($code));

        return $code;
    }

    /**
     * The public payment URL's credential. Str::random() is
     * random_bytes()-backed, so this is cryptographically sound; 48 characters
     * of it is not worth anybody's time to guess.
     */
    private function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Payment::where('token', $token)->exists());

        return $token;
    }
}
