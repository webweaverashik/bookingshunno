<?php

namespace App\Services\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Models\Payment\Payment;

/**
 * Everything the visitor's payment page needs, in one place.
 *
 * Two callers now: the portal controller renders the whole page with it, and
 * the voucher controller re-renders just the body with it after a redemption.
 * Without this the second caller would have to duplicate the state flags, and
 * the day one of them changed the page and the post-redemption refresh would
 * start disagreeing about whether a request was settled.
 */
class PaymentPortalPresenter
{
    public function __construct(private readonly SslCommerzService $gateway)
    {
    }

    public function data(Payment $payment): array
    {
        $payment->loadMissing([
            'reservation.user',
            'reservation.items.workshop',
            'transactions',
        ]);

        return [
            'payment'     => $payment,
            'reservation' => $payment->reservation,
            'contact'     => config('shunno.contact'),

            /*
             | Two separate reasons the Pay button might not appear, and the page
             | says which: the studio has switched online payment off, or it was
             | never configured. Staff can take payment by hand either way, so
             | the message points at the phone rather than at an error.
             */
            'canPayOnline' => $this->gateway->isAvailable(),

            /*
             | Three states the page speaks to. Overdue is not a fourth: the
             | studio has not asked for a missed deadline to void anything, so a
             | late visitor can still pay and the page simply says so.
             */
            'settled'   => $payment->status === PaymentStatus::Paid,
            'withdrawn' => $payment->status === PaymentStatus::Cancelled,
            'overdue'   => $payment->isOverdue(),
        ];
    }
}