<?php

namespace App\Enums;

/**
 * PHASE 13 — the life of one payment attempt.
 *
 * Phase 12B wrote a hard-coded 'success' into this column and said the other
 * cases would arrive with the gateway. They have. An online payment is not an
 * event, it is a short story: the visitor is sent to SSLCommerz, and then they
 * pay, or their card is declined, or they close the tab and never come back.
 *
 * All four outcomes are worth a row. A studio asking "why has this not been
 * paid" is far better served by "three declined attempts on Tuesday" than by
 * silence, and a support call about a card that "definitely went through" is
 * unanswerable without the failed attempts on record.
 *
 * Only Success moves money. Everything else is history.
 */
enum TransactionStatus: string
{
    case Initiated = 'initiated';
    case Success   = 'success';
    case Failed    = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Initiated => 'Started',
            self::Success   => 'Paid',
            self::Failed    => 'Failed',
            self::Cancelled => 'Abandoned',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Initiated => 'warning',
            self::Success   => 'success',
            self::Failed    => 'danger',
            self::Cancelled => 'secondary',
        };
    }

    /** Whether this attempt actually credited the payment request. */
    public function isSettled(): bool
    {
        return $this === self::Success;
    }

    /**
     * Whether the attempt is still in flight.
     *
     * An Initiated row hours old is a visitor who was sent to the gateway and
     * never came back. SSLCommerz will not always tell us, so these are closed
     * out by age rather than by waiting for a callback that is not coming.
     */
    public function isPending(): bool
    {
        return $this === self::Initiated;
    }
}
