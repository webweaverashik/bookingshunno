<?php

namespace App\Enums\Reservation;

use App\Events\Payment\PaymentRequested;

/**
 * The reservation lifecycle.
 *
 * Backed by strings, never integers: a status you can read in a database client
 * is worth more than the bytes saved, and reordering an int-backed enum
 * silently corrupts every historical row. The column is VARCHAR(32), so adding
 * a case costs no migration.
 */
enum ReservationStatus: string
{
    case Pending = 'pending';
    case InfoRequested = 'info_requested';
    case Escalated = 'escalated';
    case Approved = 'approved';
    case Declined = 'declined';
    case PaymentRequested = 'payment_requested';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::InfoRequested => 'Information requested',
            self::Escalated => 'Escalated to Admin',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
            self::PaymentRequested => 'Payment requested',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::NoShow => 'No show',
        };
    }

    /** Metronic badge modifier; the admin panel uses this from Phase 5. */
    public function colour(): string
    {
        return match ($this) {
            self::Pending, self::InfoRequested => 'warning',
            self::Escalated => 'primary',
            self::Approved, self::PaymentRequested => 'info',
            self::Confirmed, self::Completed => 'success',
            self::Declined, self::Cancelled, self::NoShow => 'danger',
        };
    }

    /**
     * Transitions the system permits. ReservationService checks this before it
     * writes, so no sequence of admin clicks can produce a nonsense history.
     *
     * Escalated sits beside Pending rather than after it: a Manager who cannot
     * approve moves the request sideways into the Admin's queue, and the Admin
     * has exactly the same options from there that a Manager had — including
     * handing it back to the review queue if it turns out not to need them.
     *
     * @return array<int,self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::InfoRequested, self::Escalated, self::Approved, self::Declined, self::Cancelled],
            self::InfoRequested => [self::Pending, self::Escalated, self::Approved, self::Declined, self::Cancelled],
            self::Escalated => [self::Pending, self::InfoRequested, self::Approved, self::Declined, self::Cancelled],
            self::Approved => [self::PaymentRequested, self::Declined, self::Cancelled],
            self::PaymentRequested => [self::Confirmed, self::Approved, self::Cancelled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled],
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
        return [self::Pending, self::InfoRequested, self::Escalated, self::Approved, self::PaymentRequested];
    }

    /**
     * Waiting on the studio rather than on the visitor or on a payment. This is
     * the "somebody here needs to look at this" set.
     */
    public static function needingDecision(): array
    {
        return [self::Pending, self::InfoRequested, self::Escalated];
    }

    /**
     * Terminal. Nothing further happens to a reservation sitting in one of
     * these, and nothing about it may be changed by anyone.
     *
     * Derived from the same fact allowedNext() states — these are exactly the
     * cases with no onward move — but named, because "closed" is the thing the
     * rest of the system actually wants to ask about, and `allowedNext() === []`
     * at six call sites is a rule nobody can find later.
     *
     * @return array<int,self>
     */
    public static function closed(): array
    {
        return [self::Declined, self::Cancelled, self::Completed, self::NoShow];
    }

    /**
     * Still in the shared review queue, where a Manager may work on it.
     *
     * Escalated is deliberately NOT here, even though it is still undecided.
     * Escalating hands a request to whoever holds reservations.approve, and a
     * Manager continuing to edit or re-route it afterwards would undo the point
     * of handing it over — the Admin would be deciding on a moving target.
     *
     * @return array<int,self>
     */
    public static function inReviewQueue(): array
    {
        return [self::Pending, self::InfoRequested];
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }

    public function isInReviewQueue(): bool
    {
        return in_array($this, self::inReviewQueue(), true);
    }
}
