<?php

namespace App\Policies\Reservation;

use App\Enums\Reservation\ReservationStatus;
use App\Models\Auth\User;
use App\Models\Reservation\Reservation;
use App\Services\Availability\AvailabilityService;

/**
 * Every ability asks two questions and both must pass:
 *
 *   1. Does this person hold the permission?
 *   2. Does the lifecycle allow this, from where the reservation is now?
 *
 * The second is delegated to the ReservationStatus enum rather than re-listed
 * here. The enum is the single authority on sequencing — restating it in a
 * policy is how a system ends up with a button that the service then refuses,
 * which reads to the admin as a bug.
 *
 * Roles are never named in this file. Approval, declining and cancelling are
 * Admin-only because RolePermissionSeeder does not give Manager those
 * permissions, which is where a question about WHO belongs. Adding
 * `! $user->hasRole('Manager')` anywhere below would put a second, divergent
 * answer in a second file, and the day the client adds a Supervisor role the
 * two would disagree and the policy would silently win.
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

    /**
     * Correct the visit — date, time, party size, notes, and for an Admin the
     * agreed price.
     *
     * Open through Confirmed on the client's instruction: bookings do change
     * after they are paid for, and the alternative to recording it is a
     * register that describes a visit nobody is going to make. Sealed once the
     * reservation closes — a completed visit is a record of what happened.
     */
    public function update(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.update')
            && $reservation->isEditable()
            && ! $this->handedUp($user, $reservation);
    }

    /**
     * Save a reservation into a slot the availability rules refuse.
     *
     * Admin only, and deliberately not given to Manager. The studio does need
     * this — someone rings up, the owner agrees to open an hour that is
     * otherwise blocked — but it is an override of the rule the rest of the
     * system trusts, so it belongs with whoever configures those rules. Every
     * use is written into the status history.
     */
    public function overrideAvailability(User $user, Reservation $reservation): bool
    {
        return $user->can('availability.update');
    }

    /**
     * Set the total to an agreed figure.
     *
     * Gated on isMoneyLocked() rather than isEditable(). The two used to be the
     * same flag; now that the visit stays correctable after a payment request,
     * the price must not follow it. Re-pricing underneath a link the visitor
     * has already been sent means the link and the record disagree about what
     * they owe — and a party-size change that re-prices is at least visible in
     * the history, which a silent new figure would not be.
     */
    public function setPrice(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.discount-override')
            && ! $reservation->isMoneyLocked()
            && $reservation->isEditable();
    }

    /**
     * Ask the visitor for money.
     *
     * The lifecycle half matters as much as the permission: false for anything
     * not sitting at Approved, so the button cannot appear on a request that
     * has not been approved or on one already awaiting payment. PaymentService
     * re-checks it under a lock regardless.
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

    /**
     * Approving is a decision taken about a request in the queue.
     *
     * The enum also permits PaymentRequested -> Approved, and that is correct:
     * withdrawing a payment request puts the reservation back to Approved. But
     * that is a consequence of withdrawing the request, not a button somebody
     * presses — and relying on canTransitionTo() alone is why an Admin was
     * being offered Approve on a reservation already awaiting payment. The
     * extra clause says what the button MEANS, which the transition table
     * cannot.
     */
    public function approve(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.approve')
            && in_array($reservation->status, ReservationStatus::needingDecision(), true)
            && $reservation->status->canTransitionTo(ReservationStatus::Approved);
    }

    /**
     * Admin only, via the seeder.
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
            && $reservation->status->canTransitionTo(ReservationStatus::InfoRequested)
            && ! $this->handedUp($user, $reservation);
    }

    /**
     * Hand the decision to an Admin.
     *
     * Offered to anyone holding the permission, including an Admin: an Admin
     * who wants the studio owner rather than themselves to make a particular
     * call has the same need, and forbidding it would be arbitrary.
     */
    public function escalate(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.escalate')
            && $reservation->status->canTransitionTo(ReservationStatus::Escalated)
            && ! $this->handedUp($user, $reservation);
    }

    /**
     * The counterpart to requestInfo and escalate: back into the review queue.
     * Same permission as requesting information — whoever may take a request
     * out of the queue may put it back.
     */
    public function returnToReview(User $user, Reservation $reservation): bool
    {
        return $user->can('reservations.request-info')
            && $reservation->status->canTransitionTo(ReservationStatus::Pending)
            && ! $this->handedUp($user, $reservation);
    }

    /**
     * Cancelling is Admin-only at every stage.
     *
     * The two permissions are both Admin-held and stay separate because they
     * are different acts: cancelling an unpaid request withdraws an offer, and
     * cancelling a paid one obliges somebody to process a refund.
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

    /**
     * The visit happened. Three conditions, all of them factual.
     *
     *   The reservation is Confirmed — the enum's only route into Completed.
     *
     *   Nothing is owed. Completing a visit with a balance outstanding would
     *   close the record on money the studio has not collected, and a closed
     *   record cannot be edited to fix that.
     *
     *   The studio has opened on the day of the visit. This is the client's
     *   rule and it is a good one: the button exists to say "they came", and
     *   offering it a week early invites somebody to tidy the register by
     *   completing a visit that has not happened.
     *
     * Loads the payments relation itself rather than trusting the caller. Every
     * other ability here reads only columns; this one reads a relation, and a
     * policy that silently returns false because nobody eager-loaded is worse
     * than one extra query on a single record.
     */
    public function complete(User $user, Reservation $reservation): bool
    {
        if (! $user->can('reservations.complete')) {
            return false;
        }

        if (! $reservation->status->canTransitionTo(ReservationStatus::Completed)) {
            return false;
        }

        $reservation->loadMissing('payments');

        if (! $reservation->hasNothingLeftToPay()) {
            return false;
        }

        return app(AvailabilityService::class)->hasOpenedOn($reservation->reserved_date);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Has this request been handed to somebody more senior than this person?
     *
     * The client's rule: once a reservation is escalated — or has moved past
     * the queue in any other way — a Manager stops acting on it. Not just
     * Approve, which they never had, but Request more information, Back to
     * review queue and Edit as well. An escalated request the Manager can still
     * bounce back into the queue is not escalated; it is on loan.
     *
     * Expressed as "cannot approve" rather than "is a Manager" on purpose.
     * Escalation means handing a decision to whoever holds
     * reservations.approve, so that permission IS the definition of the person
     * it was handed to. A future Supervisor role gets the right answer without
     * this file changing.
     */
    private function handedUp(User $user, Reservation $reservation): bool
    {
        return ! $reservation->status->isInReviewQueue()
            && ! $user->can('reservations.approve');
    }
}
