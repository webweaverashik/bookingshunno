<?php

namespace App\Events\Payment;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 12C — money has arrived.
 *
 * Raised for EVERY receipt, online or recorded by hand, because the client
 * wants both routes open and a visitor who paid cash at the studio deserves the
 * same confirmation as one who paid by card.
 *
 * Carries the transaction as well as the payment: the email links to that one
 * payslip, and reading "the latest" off the payment at send time would attach
 * the wrong receipt if two arrived close together.
 */
class PaymentReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Payment $payment,
        public PaymentTransaction $transaction,
    ) {
    }
}
