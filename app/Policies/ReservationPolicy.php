<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Auth\User;
use App\Models\Reservation;

/**
 * Every decision ability asks two questions and both must pass:
 *
 *   1. Does this person hold the permission?
 *   2. Does the lifecycle allow this move from where the reservation is now?
 *
 * The second is delegated to ReservationStatus::canTransitionTo() rather than
 * re-listed here. The enum is the single authority on sequencing — restating it
 * in a policy is how a system ends up with a button that the service then
 * refuses, which reads to the admin as a bug.
 *
 * PHASE 10A: none of these method bodies changed to implement the client's
 * escalation rule except cancel(). Approval became Admin-only by taking
 * reservations.approve away from the Manager role in RolePermissionSeeder,
 * which is where a question about WHO should be answered. That the policy did
 * not need editing is the sign the permission was named at the right level.
 *
 * PHASE 10B: declining became Admin-only the same way, and again no method body
 * changed. It is tempting to add `&& ! $user->hasRole('Manager')` to decline()
 * as a belt-and-braces measure; that would be a mistake. It puts a second,
 * divergent answer to "who may decline" in a second file, so the day the client
 * adds a Supervisor role the two disagree and the policy silently wins. The
 * role-to-permission map has one home. This file only ever asks whether the
 * permission is held and whether the lifecycle allows the move.
 */
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reservations.view');
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.view');
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.update');
    }

    /**
     * Save a reservation into a slot the availability rules refuse.
     *
     * Admin only, and deliberately not given to Manager. The studio does need
     * this — someone rings up, the owner agrees to open an hour that is
     * otherwise blocked — but it is an override of the rule the rest of the
     * system trusts, so it belongs with whoever configures those rules.
     * Every use is written into the status history.
     */
    public function overrideAvailability(User $user, Reservation $reservation): bool
    {
        return $user->can('availability.update');
    }

    /**
     * PHASE 10A — set the total to an agreed figure.
     *
     * Admin only, via reservations.discount-override, which already existed for
     * exactly this and had no user until now. Restricted to reservations that
     * are still editable: changing the price after the visitor has been sent a
     * payment link means the link and the record disagree, and Phase 12 owns
     * what should happen there.
     */
    public function setPrice(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.discount-override')
            && $reservation->isEditable();
    }

    /**
     * PHASE 12A — ask the visitor for money.
     *
     * Admin only, and again by permission rather than by role check.
     * reservations.payment-request has existed since Phase 5 and has had no
     * user until now; Manager was never given it, which is the right answer
     * under the rule 10A and 10B established — sending a payment link commits
     * the studio to a price and a deadline, and that is a decision.
     *
     * The lifecycle half matters as much as the permission: this is false for
     * anything not sitting at Approved, so the button cannot appear on a
     * request that has not been approved yet or on one already awaiting
     * payment. PaymentService re-checks it under a lock regardless.
     */
    public function requestPayment(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.payment-request')
            && $reservation->status->canTransitionTo(ReservationStatus::PaymentRequested);
    }

    /*
    |--------------------------------------------------------------------------
    | Decisions
    |--------------------------------------------------------------------------
    */

    public function approve(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.approve')
            && $reservation->status->canTransitionTo(ReservationStatus::Approved);
    }

    /**
     * PHASE 10B — Admin only, via the seeder.
     *
     * The visitor is emailed the reason verbatim, which is the argument for
     * restricting it: declining is not just a status, it is the studio telling
     * someone no in its own voice. A Manager who thinks a request should be
     * refused escalates with that reasoning in the note, and the Admin declines
     * with it.
     */
    public function decline(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.decline')
            && $reservation->status->canTransitionTo(ReservationStatus::Declined);
    }

    public function requestInfo(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.request-info')
            && $reservation->status->canTransitionTo(ReservationStatus::InfoRequested);
    }

    /**
     * PHASE 10A — hand the decision to an Admin.
     *
     * Offered to anyone holding the permission, including an Admin: an Admin
     * who wants the studio owner rather than themselves to make a particular
     * call has the same need, and forbidding it would be arbitrary.
     */
    public function escalate(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.escalate')
            && $reservation->status->canTransitionTo(ReservationStatus::Escalated);
    }

    /**
     * The counterpart to requestInfo and escalate: the request goes back into
     * the review queue. Same permission as requesting information — whoever may
     * take a request out of the queue may put it back.
     */
    public function returnToReview(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.request-info')
            && $reservation->status->canTransitionTo(ReservationStatus::Pending);
    }

    /**
     * PHASE 10A — cancelling is now Admin-only at every stage.
     *
     * Previously this fell to reservations.update before a payment existed,
     * which the client has since ruled out. The two permissions are both
     * Admin-held and stay separate because they are different acts: cancelling
     * an unpaid request is withdrawing an offer, and cancelling a paid one
     * obliges somebody to process a refund. Phase 12 will have reason to gate
     * on the second alone.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        if (! $reservation->status->canTransitionTo(ReservationStatus::Cancelled)) {
            return false;
        }

        return $reservation->isMoneyLocked()
            ? $user->can('reservations.cancel-paid')
            : $user->can('reservations.cancel');
    }
}
