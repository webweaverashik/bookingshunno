<?php

namespace App\Http\Controllers\Public\Payment;

use App\Enums\Payment\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Services\Payment\SslCommerzService;
use Illuminate\View\View;

/**
 * PHASE 12C — where the visitor pays.
 *
 * No login. The 48-character token in the URL is the credential, which is how
 * payment links work everywhere — Stripe, PayPal, SSLCommerz's own hosted pages
 * — and the alternative is asking somebody to authenticate before they can look
 * at a bill they have already been emailed.
 *
 * The page is read-only in this phase and shows nothing the visitor does not
 * already hold in that email. Phase 13 attaches the gateway to the Pay button
 * and is where signed requests and server-side verification start mattering;
 * Phase 14 attaches voucher redemption. Both are deliberately inert here rather
 * than absent, because a page that shows the amount and then offers no way to
 * pay it is more confusing than one that says "opening shortly".
 *
 * Cancelled and settled requests still render. A visitor clicking an old link
 * needs to be told what happened, and a 404 would leave them assuming the
 * studio had lost their booking.
 */
class PaymentPortalController extends Controller
{
    public function __construct(private readonly SslCommerzService $gateway)
    {
    }

    public function show(string $token): View
    {
        $payment = Payment::where('token', $token)
            ->with([
                'reservation.user',
                'reservation.items.workshop',
                'transactions',
            ])
            ->firstOrFail();

        return view('public.payment', [
            'payment'     => $payment,
            'reservation' => $payment->reservation,
            'contact'     => config('shunno.contact'),

            /*
             | Three states the page has to speak to, resolved here rather than
             | in the template so the wording sits next to the reasoning.
             |
             | Overdue is NOT a fourth state. The studio has not asked for a
             | missed deadline to void anything, so a late visitor can still
             | pay; the page tells them the date has passed and asks them to
             | check with the studio, which is honest without slamming a door
             | the client never asked to be shut.
             */
            /*
             | PHASE 13. Two separate reasons the Pay button might not appear,
             | and the page says which: the studio has switched online payment
             | off for now, or it was never configured. Staff can still take
             | payment by hand either way, so the message points at the phone
             | rather than at an error.
             */
            'canPayOnline' => $this->gateway->isAvailable(),

            'settled'   => $payment->status === PaymentStatus::Paid,
            'withdrawn' => $payment->status === PaymentStatus::Cancelled,
            'overdue'   => $payment->isOverdue(),
        ]);
    }
}
