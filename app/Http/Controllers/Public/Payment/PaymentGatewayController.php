<?php

namespace App\Http\Controllers\Public\Payment;

use App\Enums\Payment\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SslCommerzService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The SSLCommerz round trip.
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
    ) {}

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

        $attempt = null;

        try {
            $attempt = $this->payments->beginGatewayAttempt($payment);
            $redirect = $this->gateway->initiate($payment, $attempt, (float) $attempt->amount);
        } catch (RuntimeException $e) {
            /*
             | beginGatewayAttempt() commits its own transaction, so by the time
             | initiate() throws the attempt row already exists. Without this it
             | would sit at Initiated for ever: clutter in the drawer, and — as
             | the first version of this code proved — a row that later code
             | mistakes for a receipt because it is in the transactions list.
             |
             | Closed here rather than left to the stale-attempt sweep, because
             | we already KNOW it failed and why. A guess a week later is worse
             | than the reason we are holding right now.
             */
            if ($attempt) {
                $this->payments->failGatewayAttempt(
                    $attempt,
                    TransactionStatus::Failed,
                    'The gateway session could not be opened.',
                );
            }

            // Covers both a request that is no longer collectable and a gateway
            // that would not start. The visitor gets the message; the detail is
            // already in the log.
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
        /*
         | AUTHENTICITY FIRST, before anything is read or written.
         |
         | This endpoint is public, unauthenticated and CSRF-exempt, as it has
         | to be. Before this check, anybody who learned a tran_id could POST
         | status=FAILED here and the handler would mark a pending attempt as
         | failed — not a way to steal anything, but a way to break one
         | visitor's payment on demand, from anywhere, as often as they liked.
         |
         | The PAID path was never exposed: settling always went through
         | validate(), server to server, and no forged POST can make SSLCommerz
         | say VALID. It was the FAILURE path that took the callback at its word.
         |
         | 200 on rejection, not 403. SSLCommerz retries anything else, and a
         | retry will not make a forged signature correct — it would only turn
         | one bad request into a stream of them.
         */
        if (! $this->gateway->verifyIpnSignature($request->all())) {
            Log::warning('SSLCommerz IPN failed signature verification — ignored.', [
                'tran_id' => $request->input('tran_id'),
                'ip'      => $request->ip(),
            ]);

            return response('Invalid signature', 200);
        }

        $attempt = $this->attemptFrom($request);

        if (! $attempt) {
            Log::warning('SSLCommerz IPN for an unknown transaction.', [
                'tran_id' => $request->input('tran_id'),
                'ip' => $request->ip(),
            ]);

            return response('Unknown transaction', 200);
        }

        $status = strtoupper((string) $request->input('status'));

        if (in_array($status, ['VALID', 'VALIDATED'], true)) {
            $this->verifyAndSettle($attempt, $request);

            return response('OK', 200);
        }

        /*
         | The v4 docs define five IPN statuses, not three:
         | VALID, FAILED, CANCELLED, EXPIRED and UNATTEMPTED.
         |
         | UNATTEMPTED means the visitor reached the gateway and did not pick a
         | payment method — closer to backing out than to a failure, so it is
         | recorded as Cancelled. EXPIRED is a timeout and stays Failed. The
         | distinction matters in the gateway log: a run of failures suggests
         | something broken at checkout, a run of cancellations suggests
         | something wrong with the price.
         */
        $this->payments->failGatewayAttempt(
            $attempt,
            in_array($status, ['CANCELLED', 'UNATTEMPTED'], true)
                ? TransactionStatus::Cancelled
                : TransactionStatus::Failed,
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

        $this->recordRisk($attempt, $validation);
    }

    /**
     * Store SSLCommerz's fraud assessment, and shout if it is bad.
     *
     * The docs are explicit that this is the merchant's call: risk_level 1 means
     * SSLCommerz thinks the transaction is risky and leaves the decision to us.
     * It was arriving and being ignored — written into gateway_payload as part
     * of the blob, on a reservation that had already been confirmed
     * automatically, where nobody would ever see it.
     *
     * WRITTEN AFTER SETTLEMENT, NOT INSTEAD OF IT. The money moved and the
     * validation passed; refusing to record that would leave a visitor charged
     * with no booking, which is worse than a booking somebody checks by hand.
     * The flag plus a critical log entry is the right response — it puts the
     * decision in front of a person before the evening rather than after a
     * chargeback.
     *
     * A separate update rather than part of settleGatewayAttempt() because
     * that method's job is the money, and it is already the most carefully
     * locked code in the application. This is annotation.
     */
    private function recordRisk(PaymentTransaction $attempt, array $validation): void
    {
        if (! array_key_exists('risk_level', $validation)) {
            return;
        }

        $level = (int) $validation['risk_level'];

        $attempt->forceFill([
            'risk_level' => $level,
            'risk_title' => $validation['risk_title'] ?? null,
        ])->save();

        if ($level < 1) {
            return;
        }

        /*
         | critical, not warning. This is money that has arrived and a workshop
         | place that is now held, against a transaction the gateway itself is
         | unsure about — somebody should look before the visitor turns up.
         */
        Log::critical('SSLCommerz flagged a payment as risky. Review before the visit.', [
            'attempt'     => $attempt->reference,
            'reservation' => $attempt->payment?->reservation?->reference_code,
            'amount'      => $attempt->amount,
            'risk_title'  => $validation['risk_title'] ?? null,
            'card_issuer' => $validation['card_issuer'] ?? null,
            'card_country' => $validation['card_issuer_country'] ?? null,
        ]);
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
