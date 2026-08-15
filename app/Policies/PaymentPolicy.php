<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Payment;

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
 * Manager holds payments.view only, so they reach the register read-only. That
 * is deliberate and useful: whoever is on the floor needs to see whether a
 * visitor arriving tonight has paid, without being able to assert that they
 * have.
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
