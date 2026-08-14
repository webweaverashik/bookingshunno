<?php

namespace App\Policies;

use App\Models\Auth\User;
use App\Models\Reservation;

/**
 * PHASE 9 — the register.
 *
 * Deliberately narrow: this class answers "may this person see or correct a
 * reservation", and nothing else. The approval abilities exist as permissions
 * already (reservations.approve, .decline, .request-info) but are not gated
 * here because Phase 10 owns them — and a policy method that returns true for
 * an action nothing implements is a trap for whoever writes that phase.
 *
 * Whether a reservation is editable *at this point in its life* is a different
 * question from whether this person may edit reservations, and it lives on the
 * model as isEditable(). Conflating them would turn "this booking is already
 * paid for" into a bare 403, which tells the admin nothing.
 *
 * Manager holds view and update: they run day-to-day operations and correcting
 * a mistyped participant count should not wait for an Admin.
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
}
