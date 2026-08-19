<?php

namespace App\Enums\Payment;

/**
 * What the studio is asking for.
 *
 * Two cases only, per §10 of the brief. There is deliberately no
 * "second instalment" case: the client has not asked for a workflow that chases
 * the remaining half, and inventing one would mean guessing when it falls due
 * and what happens if it does not arrive. The outstanding balance is TRACKED
 * (Payment::outstandingOnReservation) so a later phase can build that
 * conversation on real data, but nothing here initiates it.
 *
 * The percentage is NOT stored on the enum. BookingFee reads its figure from
 * settings, which the client can change, and a case that hard-coded 50 would be
 * the second place that number lives. See PricingService::split().
 */
enum PaymentType: string
{
    case BookingFee = 'booking_fee';
    case Full       = 'full';

    public function label(): string
    {
        return match ($this) {
            self::BookingFee => 'Booking fee',
            self::Full       => 'Full payment',
        };
    }

    /** "Booking fee (50%)" — the phrasing the brief's payment summary uses. */
    public function describe(int $percentage): string
    {
        return "{$this->label()} ({$percentage}%)";
    }

    /**
     * Whether settling this leaves the visitor owing anything.
     *
     * Used to decide whether the admin panel shows a remaining balance at all.
     * A full payment that leaves 0.00 outstanding should not display a
     * "Remaining: BDT 0" row — that reads as a debt of zero rather than as
     * nothing owed.
     */
    public function leavesBalance(): bool
    {
        return $this === self::BookingFee;
    }
}
