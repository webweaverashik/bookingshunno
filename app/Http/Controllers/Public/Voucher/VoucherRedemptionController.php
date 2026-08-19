<?php

namespace App\Http\Controllers\Public\Voucher;

use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Voucher\Voucher;
use App\Services\Payment\PaymentPortalPresenter;
use App\Services\Payment\PaymentService;
use App\Services\Voucher\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The visitor spends a voucher against their own payment request.
 *
 * TWO STEPS, and the second one exists for a single reason: a voucher is single
 * use and all or nothing. Somebody putting a 2,000 taka gift against a 1,500
 * taka booking fee loses 500, and taking that in one click without saying so
 * would be a trap. check() validates and shows what will happen; apply() does it.
 *
 * BOTH ANSWER TWICE. A browser running the portal script gets JSON carrying
 * rendered Blade, which it swaps into the page; anything else gets the redirect
 * flow these routes have always had. The forms are real forms and the routes are
 * real routes, so a script that fails to load costs a page render, not a
 * checkout. The HTML in the JSON comes from the same partials the redirect path
 * renders, so the two cannot disagree about a figure.
 *
 * The token in the URL is the credential throughout, as it is for the portal and
 * the payslip. Nothing here reveals more than the visitor's own email already
 * contains.
 */
class VoucherRedemptionController extends Controller
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly PaymentService $payments,
        private readonly PaymentPortalPresenter $presenter,
    ) {}

    /**
     * Step one — is this code good, and what will using it do?
     *
     * Spends nothing. Everything it learns is thrown away after one render; the
     * code is re-read and re-validated in apply(), so neither a stale session
     * nor an edited page can commit anything.
     */
    public function check(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        $code = strtoupper(trim((string) $request->input('code')));

        if ($code === '') {
            return $this->refuse($request, 'Enter your voucher code.');
        }

        $voucher = Voucher::with('workshop')->where('code', $code)->first();

        if (! $voucher) {
            /*
             | Deliberately not "invalid voucher" — a dead end with no way out.
             | The hint is what is still true now that admins type their own
             | codes: case does not matter, and spaces and punctuation are
             | ignored.
             */
            return $this->refuse($request,
                'We could not find that code. Check it carefully — capitals do not matter, and you can ignore any spaces.');
        }

        try {
            $this->vouchers->assertUsable($voucher, $payment->reservation);
        } catch (RuntimeException $e) {
            // Covers expiry, prior use, café credit aimed at a reservation, and
            // a gift restricted to another experience. Each has its own message.
            return $this->refuse($request, $e->getMessage());
        }

        if (! $payment->isOpen()) {
            return $this->refuse($request, 'This payment request is closed.');
        }

        $outstanding = $payment->outstanding();
        $applies = min((float) $voucher->value, $outstanding);

        $preview = [
            'code' => $voucher->code,
            'value' => (float) $voucher->value,
            'applies' => $applies,
            'forfeit' => max(0, (float) $voucher->value - $applies),
            'remaining' => max(0, $outstanding - $applies),
            'expires' => $voucher->expires_at?->format('j M Y'),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'html' => view('public.partials.voucher-panel', [
                        'payment' => $payment,
                        'preview' => $preview,
                    ])->render(),
                ],
            ]);
        }

        return back()->with('voucher_preview', $preview);
    }

    /**
     * Step two — spend it.
     *
     * Re-reads the code from the submitted form rather than from the session,
     * and re-validates from scratch. The preview is a convenience for the person
     * reading the page; it is not evidence, and nothing here trusts it.
     */
    public function apply(Request $request, string $token): RedirectResponse|JsonResponse
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        $code = strtoupper(trim((string) $request->input('code')));
        $voucher = Voucher::where('code', $code)->first();

        if (! $voucher) {
            return $this->refuse($request, 'We could not find that code.');
        }

        try {
            /*
             | No actor. The visitor has no session here — the payment token is
             | the credential — so redeemed_by is null and the redemption note
             | carries the payment reference instead. See VoucherService::redeem().
             */
            $payment = $this->payments->settleWithVoucher($payment, $voucher);
        } catch (RuntimeException $e) {
            // Also the double-submit path: the second request finds the voucher
            // already redeemed and lands here, having changed nothing.
            return $this->refuse($request, $e->getMessage());
        }

        $settled = ! $payment->status->isOpen();

        $message = $settled
            ? "Voucher {$code} applied — your reservation is confirmed."
            : sprintf(
                'Voucher %s applied. BDT %s is still to pay.',
                $code,
                number_format($payment->outstanding()),
            );

        if ($request->expectsJson()) {
            /*
             | Re-read rather than reuse. settleWithVoucher() returns the row it
             | locked, whose relations predate the transaction it just wrote — a
             | fresh model means the receipts list and the money table describe
             | the payment as it is now, not as it was a moment ago.
             */
            $fresh = Payment::where('token', $token)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'settled' => $settled,
                    'html' => view('public.partials.payment-body', $this->presenter->data($fresh))->render(),
                ],
            ]);
        }

        return redirect()
            ->route('payment.portal', $payment->token)
            ->with($settled ? 'payment_success' : 'payment_notice', $message);
    }

    /**
     * One refusal, two shapes.
     *
     * 422 rather than 400: every one of these is the submitted code being
     * unusable, which is what that status is for, and it keeps the script's
     * error handling to a single branch.
     */
    private function refuse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->with('payment_error', $message);
    }
}
