<?php

namespace App\Http\Controllers\Public\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Services\Payment\PaymentPortalPresenter;
use Illuminate\View\View;

/**
 * Where the visitor pays.
 *
 * No login. The 48-character token in the URL is the credential, which is how
 * payment links work everywhere — and the alternative is asking somebody to
 * authenticate before they can look at a bill they have already been emailed.
 *
 * Cancelled and settled requests still render. A visitor clicking an old link
 * needs to be told what happened; a 404 would leave them assuming the studio
 * had lost their booking.
 */
class PaymentPortalController extends Controller
{
    public function __construct(private readonly PaymentPortalPresenter $presenter) {}

    public function show(string $token): View
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        return view('public.payment', $this->presenter->data($payment));
    }
}
