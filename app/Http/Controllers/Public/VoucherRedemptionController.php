<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Voucher;
use App\Services\PaymentService;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * PHASE 14C — the visitor spends a voucher against their own payment request.
 *
 * TWO STEPS, and the second one exists for a single reason: a voucher is single
 * use and all or nothing. Somebody putting a 2,000 taka gift against a 1,500
 * taka booking fee loses 500, and taking that in one click without saying so
 * would be a trap. check() validates and shows what will happen; apply() does it.
 *
 * No JavaScript. Two plain form posts and a session round trip, because the
 * payment portal is a standalone page with no script of its own and a checkout
 * that silently fails when a script does not load is worse than one extra page
 * render.
 *
 * The token in the URL is the credential throughout, as it is for the portal
 * and the payslip. Nothing here reveals more than the visitor's own email
 * already contains.
 */
class VoucherRedemptionController extends Controller
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly PaymentService $payments,
    ) {
    }

    /**
     * Step one — is this code good, and what will using it do?
     *
     * Spends nothing. Everything it learns is put in the session for one
     * render; the code is re-read and re-validated in apply(), so a stale or
     * tampered session cannot commit anything.
     */
    public function check(Request $request, string $token): RedirectResponse
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        $code = strtoupper(trim((string) $request->input('code')));

        if ($code === '') {
            return back()->with('payment_error', 'Enter your voucher code.');
        }

        $voucher = Voucher::with('workshop')->where('code', $code)->first();

        if (! $voucher) {
            // Deliberately not "invalid voucher". Our codes contain no letter O
            // and no zero, and saying so turns a dead end into a fixable typo.
            return back()->with('payment_error',
                'We could not find that code. Check it carefully — our codes never contain the letter O or the number 0.');
        }

        try {
            $this->vouchers->assertUsable($voucher, $payment->reservation);
        } catch (RuntimeException $e) {
            // Covers expiry, prior use, café credit aimed at a reservation, and
            // a gift restricted to another experience. Each has its own message.
            return back()->with('payment_error', $e->getMessage());
        }

        if (! $payment->isOpen()) {
            return back()->with('payment_error', 'This payment request is closed.');
        }

        $outstanding = $payment->outstanding();
        $applies     = min((float) $voucher->value, $outstanding);

        return back()->with('voucher_preview', [
            'code'      => $voucher->code,
            'value'     => (float) $voucher->value,
            'applies'   => $applies,
            'forfeit'   => max(0, (float) $voucher->value - $applies),
            'remaining' => max(0, $outstanding - $applies),
            'expires'   => $voucher->expires_at?->format('j M Y'),
        ]);
    }

    /**
     * Step two — spend it.
     *
     * Re-reads the code from the submitted form rather than from the session,
     * and re-validates from scratch. The preview is a convenience for the
     * person reading the page; it is not evidence, and nothing here trusts it.
     */
    public function apply(Request $request, string $token): RedirectResponse
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        $code    = strtoupper(trim((string) $request->input('code')));
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            return back()->with('payment_error', 'We could not find that code.');
        }

        try {
            $payment = $this->payments->settleWithVoucher($payment, $voucher);
        } catch (RuntimeException $e) {
            // Also the double-submit path: the second request finds the voucher
            // already redeemed and lands here, having changed nothing.
            return back()->with('payment_error', $e->getMessage());
        }

        $settled = ! $payment->status->isOpen();

        return redirect()
            ->route('payment.portal', $payment->token)
            ->with(
                $settled ? 'payment_success' : 'payment_error',
                $settled
                    ? "Voucher {$code} applied — your reservation is confirmed."
                    : sprintf(
                        'Voucher %s applied. BDT %s is still to pay.',
                        $code,
                        number_format($payment->outstanding()),
                    ),
            );
    }
}
