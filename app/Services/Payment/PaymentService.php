<?php

namespace App\Services\Payment;

use App\Enums\Payment\PaymentChannel;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Payment\TransactionStatus;
use App\Enums\Reservation\ReservationStatus;
use App\Events\Payment\PaymentReceived;
use App\Events\Payment\PaymentRequested as PaymentRequestedEvent;
use App\Models\Auth\User;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use App\Models\Reservation\Reservation;
use App\Models\Voucher\Voucher;
use App\Services\Reservation\PricingService;
use App\Services\Reservation\ReservationService;
use App\Services\Setting\SettingsRepository;
use App\Services\Voucher\VoucherService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        private readonly VoucherService $vouchers,
    ) {}

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
        $full = $this->pricing->split($total, false);

        $hours = $this->defaultDeadlineHours();

        return [
            'reservation_total' => $total,
            'default_hours' => $hours,
            'default_due_at' => $this->deadlineFrom($hours)->format('D, j M Y g:i A'),
            'has_manual_price' => $reservation->hasManualPrice(),
            'types' => [
                PaymentType::BookingFee->value => [
                    'label' => PaymentType::BookingFee->describe($booking['percentage']),
                    'percentage' => $booking['percentage'],
                    'payable' => $booking['payable'],
                    'remaining' => $booking['remaining'],
                ],
                PaymentType::Full->value => [
                    'label' => PaymentType::Full->describe($full['percentage']),
                    'percentage' => $full['percentage'],
                    'payable' => $full['payable'],
                    'remaining' => $full['remaining'],
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

            $payment = new Payment;

            $payment->forceFill([
                'reference' => $this->generateReference(),
                'token' => $this->generateToken(),
                'reservation_id' => $reservation->id,
                'type' => $type,
                'percentage' => $split['percentage'],
                'reservation_total' => $total,
                'amount_due' => $split['payable'],
                'amount_paid' => 0,
                'status' => PaymentStatus::Pending,
                'due_at' => $this->deadlineFrom($hours),
                'note' => $note,
                'requested_by' => $actor->id,
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
                    $note ? ' '.$note : '',
                ),
            );

            /*
             | PHASE 12C — dispatched INSIDE the transaction, unlike a textbook
             | afterCommit, and for the same reason transition() is called from
             | in here: SendReservationNotifications catches every Throwable
             | itself and only logs, so a mail failure cannot roll this back.
             | The reverse ordering — commit, then email — would risk a payment
             | request that exists with nobody told about it, which is worse
             | than an email about a request that failed to save, because the
             | latter cannot happen while the listener swallows its own errors.
             */
            PaymentRequestedEvent::dispatch($payment);

            return $payment->fresh(['reservation.user', 'requestedBy']);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Taking money at the counter
    |--------------------------------------------------------------------------
    */

    /**
     * Reservations that still owe the studio something.
     *
     * What the "Take payment" search on the payments register offers. The
     * register itself lists payment REQUESTS, which is why the balance left
     * after a settled booking fee was invisible there: that request is closed,
     * so it has no Record button and nothing else was looking for the money.
     *
     * Three statuses, and each is a real counter case:
     *
     *   Approved         a walk-in paying for a visit nobody has invoiced yet
     *   Payment requested a link went out and they would rather pay in person
     *   Confirmed        the booking fee is settled and this is the balance
     *
     * Declined, Cancelled and Completed are excluded on purpose. The first two
     * are not owed anything, and a completed visit is a closed record — money
     * arriving against one is a conversation, not a form.
     *
     * @return Collection<int,Reservation>
     */
    public function collectable(string $search = '', int $limit = 20): Collection
    {
        $search = trim($search);

        return Reservation::query()
            ->with(['user', 'items.workshop', 'payments'])
            ->whereIn('status', [
                ReservationStatus::Approved,
                ReservationStatus::PaymentRequested,
                ReservationStatus::Confirmed,
            ])
            ->when($search !== '', fn ($q) => $q->search($search))

            /*
             | The balance, in SQL rather than in PHP, because the alternative is
             | loading every open reservation to find the handful that owe
             | something.
             |
             | A CORRELATED SUBQUERY IN `where`, not withSum() and `having`.
             | That was the first attempt and MySQL rejects it outright —
             | "Non-grouping field 'total_override' is used in HAVING clause" —
             | because HAVING without GROUP BY may only see aggregates. WHERE is
             | where a row-by-row condition belongs anyway.
             |
             | Three things it has to get right, all of them mirroring
             | Reservation::payableTotal() and ::amountPaid():
             |
             |   COALESCE on the total, so an agreed override wins over the
             |   calculated figure
             |
             |   COALESCE on the sum, so a reservation with no payments at all
             |   reads as nothing paid rather than as null
             |
             |   CANCELLED PAYMENTS EXCLUDED. amountPaid() rejects them, and a
             |   query that counted them would show a visit as settled on the
             |   strength of a withdrawn request — the one disagreement between
             |   this list and the summary card that would actually lose money.
             |
             | The 0.009 is the same poisha-sized tolerance used everywhere money
             | is compared here — a balance of 0.004 is paid.
             */
            ->whereRaw(
                'COALESCE(reservations.total_override, reservations.total_amount) - COALESCE((
                    select sum(payments.amount_paid)
                      from payments
                     where payments.reservation_id = reservations.id
                       and payments.status <> ?
                ), 0) > 0.009',
                [PaymentStatus::Cancelled->value],
            )

            ->orderBy('reserved_date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Take money at the counter, whether or not a request exists for it.
     *
     * The studio's actual problem: a booking fee settles, the visitor turns up
     * and hands over the balance, and there is no open request to record it
     * against. Staff were left with a reservation showing an outstanding amount
     * and no way to say it had been paid.
     *
     * TWO PATHS, and which one runs is decided here rather than by whoever is
     * standing at the till:
     *
     *   An OPEN request already exists — record against it. A second request
     *   for money already asked for would leave two links for one debt and two
     *   documents describing it.
     *
     *   No open request — raise one for the balance and settle it in the same
     *   transaction. It is created already due and is never emailed: the
     *   visitor is standing in front of somebody, and "please pay" arriving
     *   about money just handed over is worse than useless.
     *
     * The receipt email still goes out, because that is the payslip. It is the
     * one message the visitor actually wants from this.
     *
     * @throws RuntimeException when there is nothing to collect, or the amount
     *                          is more than is owed.
     */
    public function collect(
        Reservation $reservation,
        float $amount,
        PaymentMethod $method,
        User $actor,
        ?string $reference = null,
        ?CarbonImmutable $paidAt = null,
        ?string $note = null,
    ): Payment {
        return DB::transaction(function () use ($reservation, $amount, $method, $actor, $reference, $paidAt, $note) {
            // Locked before anything is decided. Two staff at two tills on the
            // same reservation is unlikely and not impossible, and the loser of
            // that race must fail cleanly rather than raise a second request.
            $reservation = Reservation::whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $reservation->load('payments');

            if ($reservation->status->isClosed()) {
                throw new RuntimeException(
                    "This reservation is {$reservation->status->label()} and is not owed anything."
                );
            }

            $open = $reservation->payments()->open()->first();

            if ($open) {
                // Straight down the existing path — same guards, same receipt,
                // same settlement arithmetic.
                return $this->record($open, $amount, $method, $actor, $reference, $paidAt, $note);
            }

            $balance = $reservation->outstandingTotal();

            if ($balance <= 0.009) {
                throw new RuntimeException('This reservation has nothing outstanding.');
            }

            if ((int) round($amount * 100) > (int) round($balance * 100)) {
                throw new RuntimeException(sprintf(
                    'That is more than the BDT %s still outstanding on this reservation.',
                    number_format($balance),
                ));
            }

            $payment = new Payment;

            $payment->forceFill([
                'reference' => $this->generateReference(),
                'token' => $this->generateToken(),

                'reservation_id' => $reservation->id,

                /*
                 | Full, at 100% of what is left. Not BookingFee: the type
                 | describes what this request asks for, and this one asks for
                 | the whole remaining balance. leavesBalance() is therefore
                 | false and the payslip correctly shows no "payable at the
                 | studio" line — there is nothing after this.
                 */
                'type' => PaymentType::Full,
                'percentage' => 100,

                'reservation_total' => $reservation->payableTotal(),
                'amount_due' => $balance,
                'amount_paid' => 0,
                'status' => PaymentStatus::Pending,

                // Already due. The money is on the counter; a deadline in three
                // days would be a fiction, and an overdue badge on a request
                // settled thirty seconds later would be noise.
                'due_at' => CarbonImmutable::now(),

                'note' => $note,
                'requested_by' => $actor->id,
            ])->save();

            /*
             | A reservation still at Approved has to reach Payment requested
             | before it can be Confirmed — that is the enum's route and
             | applySettlement() follows it. Done here, quietly, with a history
             | line saying why: no PaymentRequestedEvent, so no "please pay"
             | email chasing money that has already been handed over.
             */
            if ($reservation->status === ReservationStatus::Approved) {
                $this->reservations->transition(
                    $reservation,
                    ReservationStatus::PaymentRequested,
                    $actor,
                    sprintf(
                        'Payment taken at the studio: BDT %s of BDT %s. Reference %s. No payment link was sent.',
                        number_format($amount),
                        number_format($balance),
                        $payment->reference,
                    ),
                );
            }

            return $this->record($payment, $amount, $method, $actor, $reference, $paidAt, $note);
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

            $this->guardCollectable($payment, $amount);

            /*
             | PHASE 12B — the receipt. PHASE 13 moved the money arithmetic out
             | into applySettlement(), which the gateway path also uses, so that
             | a card payment and a cash payment cannot drift apart in how they
             | credit a request.
             */
            $transaction = new PaymentTransaction;

            $transaction->forceFill([
                'reference' => $this->generateTransactionReference(),
                'payment_id' => $payment->id,
                'channel' => PaymentChannel::forMethod($method),
                'method' => $method,
                'status' => TransactionStatus::Success,
                'amount' => round($amount, 2),
                'external_reference' => $reference,
                'note' => $note,
                'received_at' => $paidAt ?? CarbonImmutable::now(),
                'recorded_by' => $actor->id,
            ])->save();

            return $this->applySettlement($payment, $transaction, $actor);
        });
    }

    /**
     * PHASE 13 — credit a settled attempt to its request.
     *
     * The single place money moves, whichever way it arrived. Assumes the
     * transaction row already exists and is genuinely settled: record() writes
     * one and calls straight through, and the gateway path validates first and
     * then calls the same method. Everything downstream of "money is real" —
     * the running total, the request status, the reservation transition, the
     * receipt's snapshot, the visitor's email — happens here, once.
     *
     * @param  User|null  $actor  Null for a gateway settlement, where nobody
     *                            asserted anything; the history line says the
     *                            gateway did it.
     */
    private function applySettlement(Payment $payment, PaymentTransaction $transaction, ?User $actor): Payment
    {
        $amount = (float) $transaction->amount;

        // Poisha throughout, never floats. 4999.995 compares as "greater than"
        // 4999.99 in floating point often enough to matter when somebody pays
        // the outstanding amount exactly.
        $outstandingPoisha = (int) round($payment->outstanding() * 100);
        $amountPoisha = (int) round($amount * 100);
        $paidPoisha = (int) round((float) $payment->amount_paid * 100) + $amountPoisha;
        $settled = $paidPoisha >= (int) round((float) $payment->amount_due * 100);

        $payment->forceFill([
            'amount_paid' => $paidPoisha / 100,
            'method' => $transaction->method,
            'gateway_reference' => $transaction->external_reference,
            'recorded_by' => $actor?->id,
            'status' => $settled ? PaymentStatus::Paid : PaymentStatus::Pending,
            'paid_at' => $settled ? ($transaction->received_at ?? CarbonImmutable::now()) : null,
        ])->save();

        // balance_after is snapshotted rather than derived. A receipt the
        // visitor is holding must say the same thing next month, and
        // recomputing "still to come" at render time would rewrite it the
        // moment anything else moved.
        $transaction->forceFill([
            'balance_after' => max(0, $outstandingPoisha - $amountPoisha) / 100,
        ])->save();

        $reservation = $payment->reservation()->lockForUpdate()->firstOrFail();

        $line = sprintf(
            'BDT %s received by %s against %s. Receipt %s.%s%s',
            number_format($amount),
            $transaction->method->label(),
            $payment->reference,
            $transaction->reference,
            $transaction->external_reference ? " Ref {$transaction->external_reference}." : '',
            $transaction->note ? ' '.$transaction->note : '',
        );

        if (! $settled) {
            // Part paid. The reservation stays where it is — it is not
            // confirmed until the amount asked for has actually arrived — and
            // the history says how much is left so nobody has to work it out
            // from two other rows.
            $this->reservations->note(
                $reservation,
                $actor,
                $line.sprintf(' BDT %s still outstanding on this request.', number_format($payment->outstanding())),
            );

            /*
             | ONE RECEIPT PER REQUEST, not one per transaction.
             |
             | A voucher that does not cover the whole amount is not a payment
             | the visitor needs telling about — they are still on the payment
             | page, about to pay the rest, and an email arriving mid-checkout
             | saying "payment received" for a coupon they just typed is
             | confusing at best. Worse, it produced a second payslip: the
             | client reported two documents and two emails for a single
             | booking, one for the coupon and one for the card.
             |
             | The payslip is a statement of the whole REQUEST now — it lists
             | every receipt, the coupon among them — so waiting until the
             | request is settled loses nothing. When the card payment lands,
             | one email goes out and its payslip shows both lines.
             |
             | Real money arriving as a part payment still announces itself. If
             | somebody hands over half at the counter, they get a receipt for
             | it then and there, because the next one might be weeks away.
             */
            if ($transaction->channel !== PaymentChannel::Voucher) {
                PaymentReceived::dispatch($payment, $transaction);
            }

            return $payment->fresh(['reservation.user', 'recordedBy', 'transactions']);
        }

        if ($reservation->status === ReservationStatus::Confirmed) {
            /*
             | Already confirmed, and the money is the balance being settled —
             | the booking fee closed one request, this closes another. There is
             | nothing to transition to and nothing wrong; without this branch it
             | fell through to the refund warning below and told staff a
             | perfectly ordinary counter payment might need refunding.
             */
            $this->reservations->note($reservation, $actor, $line);
        } elseif ($reservation->status->canTransitionTo(ReservationStatus::Confirmed)) {
            $this->reservations->transition(
                $reservation,
                ReservationStatus::Confirmed,
                $actor,
                $line,
            );
        } else {
            /*
             | Money arrived against a reservation that is no longer waiting for
             | it — cancelled while the visitor was at the gateway, most likely.
             | The receipt is still recorded, because it happened and pretending
             | otherwise loses real money from the books, but the status is left
             | alone and the history says so plainly. Somebody has a refund to
             | process and this is how they find out.
             */
            $this->reservations->note(
                $reservation,
                $actor,
                $line." The reservation is {$reservation->status->label()}, so it has not been confirmed. This may need refunding.",
            );
        }

        /*
         | PHASE 14A — the café coupon, issued only once the request is settled.
         |
         | On settlement, not on approval: the client's rule is that credit is
         | earned by a paid visit, and issuing it earlier would let somebody
         | collect coupons by requesting bookings they never pay for.
         |
         | Note what this does NOT do — it does not touch the reservation total.
         | Café credit is a thank-you spent at the counter, not a discount on the
         | thing that earned it. That is why it is issued here, after the money
         | has been counted, rather than anywhere near PricingService.
         |
         | issueCafeCredit() returns null for an experience that earns nothing,
         | which is the ordinary case and not a failure. It also swallows a
         | duplicate — SSLCommerz can settle the same payment twice, via the
         | redirect and the IPN — so a repeated call cannot mint a second coupon
         | or roll back a real payment.
         */
        $this->vouchers->issueCafeCredit($reservation->load('items.workshop'), $payment);

        PaymentReceived::dispatch($payment, $transaction);

        return $payment->fresh(['reservation.user', 'recordedBy', 'transactions']);
    }

    /**
     * PHASE 13 — the shared guard for anything about to take money.
     *
     * Run under the row lock in every caller. Throws rather than returning
     * false because each message is something a person needs to READ: an admin
     * typing a figure, or a visitor who has just been told their payment could
     * not start.
     */
    private function guardCollectable(Payment $payment, float $amount): void
    {
        if (! $payment->isOpen()) {
            throw new RuntimeException(
                "This payment request is {$payment->status->label()} — nothing further can be recorded against it."
            );
        }

        if ($amount <= 0) {
            throw new RuntimeException('The amount received has to be more than zero.');
        }

        if ((int) round($amount * 100) > (int) round($payment->outstanding() * 100)) {
            throw new RuntimeException(sprintf(
                'That is more than the BDT %s still outstanding on this request.',
                number_format($payment->outstanding()),
            ));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Gateway (Phase 13)
    |--------------------------------------------------------------------------
    */

    /**
     * Open an attempt before sending the visitor to SSLCommerz.
     *
     * The row has to exist FIRST. Its reference becomes the tran_id, which is
     * what every callback quotes back — so a response can always be tied to
     * exactly one attempt, and a repeated callback is recognisable as a repeat
     * rather than a second payment. Creating the row afterwards, from whatever
     * the callback claims, is how a gateway integration ends up crediting the
     * same money twice.
     *
     * Always for the FULL outstanding amount. Part payment is something the
     * studio agrees with somebody face to face; a checkout page where a visitor
     * types their own figure is a different feature and nobody has asked for it.
     */
    public function beginGatewayAttempt(Payment $payment): PaymentTransaction
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $amount = $payment->outstanding();

            $this->guardCollectable($payment, $amount);

            $attempt = new PaymentTransaction;

            $attempt->forceFill([
                'reference' => $this->generateTransactionReference(),
                'payment_id' => $payment->id,
                'channel' => PaymentChannel::Gateway,
                'method' => PaymentMethod::Sslcommerz,
                'status' => TransactionStatus::Initiated,
                'amount' => round($amount, 2),

                // Both null until it succeeds. A row with no received_at is not
                // a receipt, and the payslip route will not render one.
                'received_at' => null,
                'balance_after' => null,
            ])->save();

            return $attempt;
        });
    }

    /**
     * Credit an attempt that SSLCommerz has confirmed, server to server.
     *
     * IDEMPOTENT, and that is the whole point. SSLCommerz fires both a browser
     * redirect and a server-side IPN for the same payment, sometimes seconds
     * apart and sometimes in either order, and it will retry the IPN if we are
     * slow to answer. Every one of those paths lands here. The lock plus the
     * status check means the first caller settles it and the rest quietly get
     * the same answer.
     *
     * @param  array<string,mixed>  $validation  The payload from
     *                                           SslCommerzService::validate().
     *                                           Never anything the browser sent.
     */
    public function settleGatewayAttempt(PaymentTransaction $attempt, array $validation): Payment
    {
        return DB::transaction(function () use ($attempt, $validation) {
            $attempt = PaymentTransaction::whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();

            // Already done. Not an error — it is the second callback arriving,
            // which is normal and expected.
            if ($attempt->status === TransactionStatus::Success) {
                return $payment;
            }

            $attempt->forceFill([
                'status' => TransactionStatus::Success,
                'external_reference' => $validation['bank_tran_id'] ?? ($validation['val_id'] ?? null),
                'gateway_val_id' => $validation['val_id'] ?? null,
                'gateway_bank_tran_id' => $validation['bank_tran_id'] ?? null,
                'gateway_card_type' => $validation['card_type'] ?? null,
                'gateway_payload' => $validation,
                'validated_at' => CarbonImmutable::now(),
                'received_at' => CarbonImmutable::now(),
            ])->save();

            /*
             | Guarded AFTER the attempt is marked settled, deliberately.
             |
             | If the request was withdrawn or already paid while the visitor
             | was on the gateway's page, the money still arrived and the record
             | of it must survive — losing it would mean a visitor is out of
             | pocket with nothing in our books. So the attempt keeps its
             | Success and its payload, and applySettlement is simply skipped;
             | the payment's own note trail already covers the refund case for
             | money that lands against a dead reservation.
             */
            if (! $payment->isOpen()) {
                Log::warning('A gateway payment settled against a request that was no longer open.', [
                    'payment' => $payment->reference,
                    'attempt' => $attempt->reference,
                    'status' => $payment->status->value,
                ]);

                return $payment;
            }

            return $this->applySettlement($payment, $attempt, null);
        });
    }

    /**
     * Close an attempt that did not result in money.
     *
     * Kept rather than deleted. "Three declined attempts on Tuesday" is the
     * answer to a support call that silence cannot give, and a visitor
     * insisting their card went through is unanswerable without it.
     */
    public function failGatewayAttempt(
        PaymentTransaction $attempt,
        TransactionStatus $status,
        ?string $reason = null,
        ?array $payload = null,
    ): PaymentTransaction {
        if ($attempt->status !== TransactionStatus::Initiated) {
            // A late fail callback after a successful IPN. Ignore it — the
            // money is real and this would otherwise undo it.
            return $attempt;
        }

        $attempt->forceFill([
            'status' => $status,
            'failure_reason' => $reason,
            'gateway_payload' => $payload,
        ])->save();

        return $attempt;
    }

    /**
     * PHASE 14C — settle a request, in whole or in part, with a voucher.
     *
     * ORDER MATTERS. The voucher is redeemed FIRST, inside this transaction,
     * because that is the operation with a lock on it and the one that must not
     * happen twice. A visitor double-submitting the form has both requests reach
     * VoucherService::redeem(); the second finds a row already marked Redeemed
     * and throws, and this whole transaction — receipt included — rolls back.
     * Crediting the payment first and redeeming afterwards would let two
     * receipts exist for one coupon.
     *
     * SINGLE USE, ALL OR NOTHING, per the client's decision. A 2,000 taka
     * voucher against a 1,500 taka request settles the 1,500 and forfeits the
     * rest — so the portal makes the visitor confirm that in words before it
     * gets here. The amount recorded is capped at what is outstanding, because
     * a receipt for more than was owed would put the payment into a state
     * guardCollectable() exists to prevent.
     *
     * @throws RuntimeException when the voucher cannot be used, or the request
     *                          cannot take it.
     */
    public function settleWithVoucher(Payment $payment, Voucher $voucher, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $voucher, $actor) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            $reservation = $payment->reservation;

            // Checked before the voucher is spent, so a request that has since
            // been withdrawn does not burn somebody's coupon on the way to
            // being refused.
            if (! $payment->isOpen()) {
                throw new RuntimeException(
                    "This payment request is {$payment->status->label()} and cannot take a voucher."
                );
            }

            $amount = min((float) $voucher->value, $payment->outstanding());

            $this->guardCollectable($payment, $amount);

            // Redeems under its own lock and re-checks type, expiry and any
            // workshop restriction. Throws with a message meant for the visitor.
            $this->vouchers->redeem($voucher, $actor, $reservation, sprintf(
                'Applied to %s (%s).',
                $payment->reference,
                $reservation?->reference_code ?? 'reservation',
            ));

            $transaction = new PaymentTransaction;

            $transaction->forceFill([
                'reference' => $this->generateTransactionReference(),
                'payment_id' => $payment->id,
                'channel' => PaymentChannel::Voucher,
                'method' => PaymentMethod::Voucher,
                'status' => TransactionStatus::Success,
                'amount' => round($amount, 2),
                'external_reference' => $voucher->code,
                'note' => sprintf(
                    'Voucher %s worth BDT %s.%s',
                    $voucher->code,
                    number_format((float) $voucher->value),
                    (float) $voucher->value > $amount
                        ? ' BDT '.number_format((float) $voucher->value - $amount).' of it was not needed and is now spent.'
                        : '',
                ),
                'received_at' => CarbonImmutable::now(),
                'recorded_by' => $actor?->id,
            ])->save();

            return $this->applySettlement($payment, $transaction, $actor);
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
                'status' => PaymentStatus::Cancelled,
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
            $code = $prefix.'-'.now()->format('ym').'-'.$suffix;
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
