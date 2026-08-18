<?php

namespace App\Events\Payment;

use App\Models\Payment\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 12C — the studio has asked a visitor for money.
 *
 * A payment event rather than a reservation status event, even though a status
 * change happens at the same moment. ReservationStatusChanged carries only a
 * from, a to and a note; this email needs an amount, a deadline and a link, and
 * widening that event to carry an optional Payment would force every other
 * listener to handle a null it does not care about.
 *
 * ReservationMailKind::forStatus() therefore still returns null for
 * PaymentRequested. That is load-bearing: if it ever returns a kind, the status
 * listener and this one both fire and the visitor gets the same email twice.
 */
class PaymentRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Payment $payment)
    {
    }
}
