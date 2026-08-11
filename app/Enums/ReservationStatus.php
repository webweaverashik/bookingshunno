<?php

namespace App\Enums;

/**
 * The reservation lifecycle.
 *
 * Backed by strings, never integers: a status you can read in a database client
 * is worth more than the bytes saved, and reordering an int-backed enum
 * silently corrupts every historical row.
 */
enum ReservationStatus: string
{
    case Pending          = 'pending';
    case InfoRequested    = 'info_requested';
    case Approved         = 'approved';
    case Declined         = 'declined';
    case PaymentRequested = 'payment_requested';
    case Confirmed        = 'confirmed';
    case Cancelled        = 'cancelled';
    case Completed        = 'completed';
    case NoShow           = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending          => 'Pending review',
            self::InfoRequested    => 'Information requested',
            self::Approved         => 'Approved',
            self::Declined         => 'Declined',
            self::PaymentRequested => 'Payment requested',
            self::Confirmed        => 'Confirmed',
            self::Cancelled        => 'Cancelled',
            self::Completed        => 'Completed',
            self::NoShow           => 'No show',
        };
    }

    /** Metronic badge modifier; the admin panel uses this from Phase 5. */
    public function colour(): string
    {
        return match ($this) {
            self::Pending, self::InfoRequested             => 'warning',
            self::Approved, self::PaymentRequested         => 'info',
            self::Confirmed, self::Completed               => 'success',
            self::Declined, self::Cancelled, self::NoShow  => 'danger',
        };
    }

    /**
     * Transitions the system permits. ReservationService checks this before it
     * writes, so no sequence of admin clicks can produce a nonsense history.
     *
     * @return array<int,self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending          => [self::InfoRequested, self::Approved, self::Declined, self::Cancelled],
            self::InfoRequested    => [self::Pending, self::Approved, self::Declined, self::Cancelled],
            self::Approved         => [self::PaymentRequested, self::Declined, self::Cancelled],
            self::PaymentRequested => [self::Confirmed, self::Approved, self::Cancelled],
            self::Confirmed        => [self::Completed, self::NoShow, self::Cancelled],
            self::Declined, self::Cancelled, self::Completed, self::NoShow => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Statuses still waiting on someone. Drives the admin dashboard queue. */
    public static function open(): array
    {
        return [self::Pending, self::InfoRequested, self::Approved, self::PaymentRequested];
    }
}
