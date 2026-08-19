<?php

namespace App\Events\Reservation;

use App\Enums\Reservation\ReservationStatus;
use App\Models\Auth\User;
use App\Models\Reservation\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A reservation moved from one status to another.
 *
 * Carries the note alongside the statuses. The note is the reason for a
 * decline, the question being asked of a visitor, or what an Admin is being
 * asked to decide — in other words, the only part of the email that is not
 * derivable from the reservation itself. Reading it back out of the status
 * history instead would work today and break the moment two things happen in
 * the same second.
 *
 * Raised AFTER the transaction commits, so a listener can never roll back a
 * decision that has already been recorded.
 */
class ReservationStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public ReservationStatus $from,
        public ReservationStatus $to,
        public ?User $actor = null,
        public ?string $note = null,
    ) {
    }
}
