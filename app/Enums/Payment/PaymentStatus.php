<?php

namespace App\Enums\Payment;

/**
 * Where a payment request stands.
 *
 * Three cases. An "Expired" case was drafted and removed: nothing in the system
 * would ever set it. Auto-expiry needs a business rule the client has not given
 * — does a missed deadline free the slot, or does someone ring the visitor? —
 * and §25 rules out inventing one where money and reservation status are
 * involved. So a deadline that passes leaves the request Pending and OVERDUE,
 * which the register surfaces loudly, and a human decides. If the client later
 * wants automatic expiry, this enum gains a case and a scheduled command sets
 * it; until then a state nobody writes is just a lie in a dropdown.
 *
 * Partial payment has no case either, for a different reason: it is not a state
 * but an arithmetic fact about amount_paid, and Payment::isPartiallyPaid()
 * derives it. A request stays Pending until it is covered. That matters because
 * the studio does take money in pieces — 2,000 in cash now, the rest on the day
 * — and a separate status would need its own transition rules for no gain.
 */
enum PaymentStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending   => 'Awaiting payment',
            self::Paid      => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Metronic badge modifier. */
    public function colour(): string
    {
        return match ($this) {
            self::Pending   => 'warning',
            self::Paid      => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Still owed, still collectable, still holding the reservation. */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    /** @return array<int,self> */
    public static function open(): array
    {
        return [self::Pending];
    }
}
