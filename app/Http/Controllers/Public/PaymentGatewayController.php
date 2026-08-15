<?php

namespace App\Http\Controllers\Public;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use App\Services\SslCommerzService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * PHASE 13 — the SSLCommerz round trip.
 *
 * Five endpoints. Four of them are entered by somebody who is not logged in,
 * carrying data we did not write, so the governing rule throughout is: the
 * request tells us WHICH attempt to look at, and nothing else. Whether it was
 * paid is answered by asking SSLCommerz directly.
 *
 *   start()    our page, our session — sends the visitor out
 *   success()  the visitor's browser comes back. Proves nothing on its own.
 *   fail()     the gateway says it did not work
 *   cancel()   the visitor backed out
 *   ipn()      server to server, no browser, no session. The reliable one.
 *
 * WHY SUCCESS AND IPN BOTH SETTLE. The redirect is fast but unreliable — a
 * visitor closing the tab at the wrong moment never triggers it. The IPN is
 * reliable but can lag, and SSLCommerz retries it. Handling only one leaves
 * either a paid visitor staring at an unpaid page, or a paid reservation nobody
 * noticed. Both are wired, and settleGatewayAttempt() is idempotent so whichever
 * arrives second does nothing.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly SslCommerzService $gateway,
        private readonly PaymentService $payments,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Leaving
    |--------------------------------------------------------------------------
    */

    public function start(string $token): RedirectResponse
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        if (! $this->gateway->isAvailable()) {
            return back()->with('payment_error',
                'Online payment is unavailable at the moment. Please contact the studio and we will take payment directly.');
        }

        try {
            $attempt  = $this->payments->beginGatewayAttempt($payment);
            $redirect = $this->gateway->initiate($payment, $attempt, (float) $attempt->amount);
        } catch (RuntimeException $e) {
            // Covers both a request that is no longer collectable and a gateway
            // that would not open a session. The visitor gets the message; the
            // detail is already in the log.
            return back()->with('payment_error', $e->getMessage());
        }

        // away() rather than to(): this is an absolute URL on somebody else's
        // domain, and to() would prefix it with ours.
        return redirect()->away($redirect);
    }

    /*
    |--------------------------------------------------------------------------
    | Coming back
    |--------------------------------------------------------------------------
    */

    /**
     * The visitor's browser returns here.
     *
     * §11: reaching this URL is NOT payment. It can be typed by hand, replayed
     * from history, or shared. All it is trusted to do is name an attempt; the
     * money question is put to SSLCommerz over a separate connection.
     */
    public function success(Request $request): RedirectResponse
    {
        $attempt = $this->attemptFrom($request);

        if (! $attempt) {
            return redirect()->route('home')
                ->with('payment_error', 'We could not match that payment. Please contact the studio.');
        }

        $this->verifyAndSettle($attempt, $request);

        $payment = $attempt->payment->fresh();

        return redirect()->route('payment.portal', $payment->token)->with(
            $payment->status->isOpen() ? 'payment_error' : 'payment_success',
            $payment->status->isOpen()
                ? 'We could not confirm that payment with the gateway. If money has left your account, contact the studio and we will sort it out.'
                : 'Thank you — your payment is confirmed.',
        );
    }

    public function fail(Request $request): RedirectResponse
    {
        return $this->closeAttempt(
            $request,
            TransactionStatus::Failed,
            'That payment did not go through. Nothing has been charged — you can try again.',
        );
    }

    public function cancel(Request $request): RedirectResponse
    {
        return $this->closeAttempt(
            $request,
            TransactionStatus::Cancelled,
            'Payment cancelled. Nothing has been charged.',
        );
    }

    /**
     * Server-to-server notification. No browser, no session, no CSRF token.
     *
     * The reliable half of the pair, and the one that catches the visitor who
     * paid and then closed the tab. Answers 200 in every case that is not our
     * own fault: SSLCommerz retries on anything else, and retrying will not fix
     * a callback for a transaction that does not exist.
     */
    public function ipn(Request $request): Response
    {
        $attempt = $this->attemptFrom($request);

        if (! $attempt) {
            Log::warning('SSLCommerz IPN for an unknown transaction.', [
                'tran_id' => $request->input('tran_id'),
                'ip'      => $request->ip(),
            ]);

            return response('Unknown transaction', 200);
        }

        $status = strtoupper((string) $request->input('status'));

        if (in_array($status, ['VALID', 'VALIDATED'], true)) {
            $this->verifyAndSettle($attempt, $request);

            return response('OK', 200);
        }

        $this->payments->failGatewayAttempt(
            $attempt,
            $status === 'CANCELLED' ? TransactionStatus::Cancelled : TransactionStatus::Failed,
            $request->input('error') ?: "Gateway reported {$status}",
            $request->all(),
        );

        return response('OK', 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The one thing the callback is trusted for: which attempt this is about.
     *
     * tran_id is our own reference, generated before the visitor left, so this
     * is a lookup rather than an assertion. A tran_id we never issued finds
     * nothing, which is the correct outcome.
     */
    private function attemptFrom(Request $request): ?PaymentTransaction
    {
        $tranId = (string) $request->input('tran_id');

        if ($tranId === '') {
            return null;
        }

        return PaymentTransaction::with('payment')
            ->where('reference', $tranId)
            ->first();
    }

    /**
     * Ask SSLCommerz whether this really happened, then settle if so.
     *
     * The amount checked against is the one WE recorded when the attempt was
     * opened, never the one the callback volunteers. That is the check that
     * stops a tampered redirect settling a 3,000 taka workshop for 10 taka.
     */
    private function verifyAndSettle(PaymentTransaction $attempt, Request $request): void
    {
        if ($attempt->status === TransactionStatus::Success) {
            return;     // The other callback got here first.
        }

        $valId = (string) $request->input('val_id');

        if ($valId === '') {
            Log::warning('SSLCommerz callback carried no val_id.', [
                'attempt' => $attempt->reference,
            ]);

            return;
        }

        $validation = $this->gateway->validate($valId, $attempt, (float) $attempt->amount);

        if ($validation === null) {
            // validate() has already logged why, at warning or critical
            // depending on whether it looked like tampering.
            $this->payments->failGatewayAttempt(
                $attempt,
                TransactionStatus::Failed,
                'Could not be verified with the gateway.',
                $request->all(),
            );

            return;
        }

        $this->payments->settleGatewayAttempt($attempt, $validation);
    }

    private function closeAttempt(Request $request, TransactionStatus $status, string $message): RedirectResponse
    {
        $attempt = $this->attemptFrom($request);

        if (! $attempt) {
            return redirect()->route('home')->with('payment_error', $message);
        }

        $this->payments->failGatewayAttempt(
            $attempt,
            $status,
            $request->input('error') ?: null,
            $request->all(),
        );

        return redirect()
            ->route('payment.portal', $attempt->payment->token)
            ->with('payment_error', $message);
    }
}
