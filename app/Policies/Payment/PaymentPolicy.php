<?php

namespace App\Policies\Payment;

use App\Models\Auth\User;
use App\Models\Payment\Payment;

/**
 * PHASE 12A.
 *
 * Note what is NOT here: a create() ability. Creating a payment is something
 * you do TO a reservation, and the question "may this person charge for this
 * visit" depends on where the reservation sits, not on a Payment that does not
 * exist yet. It lives on ReservationPolicy::requestPayment() instead.
 *
 * Both write abilities run on payments.update-status rather than a new
 * permission. The seeder already carries payments.view, payments.verify and
 * payments.update-status; verify is reserved for Phase 13, where it will mean
 * "trust a gateway callback" — a genuinely different act from "a human says
 * this money arrived", and one worth being able to grant separately.
 *
 * Manager now holds payments.update-status as well, on the client's
 * instruction that they may take payment offline. No method here changed — the
 * seeder answered it, as in 10A and 10B. Manager still cannot approve, decline
 * or cancel a reservation; recording money that arrived is a fact, not a
 * decision about whether the studio wants the booking.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.view');
    }

    /**
     * Write down money that arrived outside the gateway.
     *
     * Gated on the payment still being open as well as on the permission: a
     * settled request has nothing left to record, and a cancelled one should
     * not quietly come back to life because somebody had a stale drawer open.
     * PaymentService checks the same thing under a lock — this half only
     * decides whether the button is drawn.
     */
    public function record(User $user, Payment $payment): bool
    {
        return $user->can('payments.update-status') && $payment->isOpen();
    }

    /**
     * Take money at the counter, against a reservation rather than against a
     * particular request.
     *
     * A class-level twin of record(), needed because there is no payment to
     * pass: the whole point of the Take payment button is the case where no
     * open request exists and one has to be raised for the balance. Same
     * permission, so a Manager who can write down money against a request can
     * write it down against a reservation, which is the same act performed at
     * the till instead of at a desk.
     *
     * Whether there is anything to collect is the service's question — it has
     * to read the balance under a lock — and this only decides whether the
     * button is drawn.
     */
    public function recordAny(User $user): bool
    {
        return $user->can('payments.update-status');
    }

    /**
     * Withdraw a request.
     *
     * The additional rule that money must not already have been received lives
     * in the service rather than here, because it produces a message the admin
     * needs to READ — "refund it before cancelling" — and a policy that simply
     * hid the button would leave them wondering where it went.
     */
    public function cancel(User $user, Payment $payment): bool
    {
        return $user->can('payments.update-status') && $payment->isOpen();
    }
}
