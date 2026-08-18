<?php

namespace App\Events\Reservation;

use App\Models\Reservation\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 11 — a visitor has submitted a request from the website.
 *
 * Separate from ReservationStatusChanged even though a new reservation does
 * land on Pending. The two are different facts: this one says "something new
 * arrived", the other says "something moved". A later phase that wants to count
 * incoming requests, or notify the studio that one arrived, wants this one and
 * would otherwise have to filter status changes for from_status === null.
 *
 * Raised AFTER the creating transaction commits — see ReservationService.
 */
class ReservationRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Reservation $reservation)
    {
    }
}
