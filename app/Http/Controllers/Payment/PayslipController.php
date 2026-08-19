<?php

namespace App\Http\Controllers\Payment;

use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use App\Http\Controllers\Controller;

/**
 * PHASE 12B — the payslip.
 *
 * One controller and one view for two audiences, reached by two different
 * routes with two different credentials:
 *
 *   staff()    admin/payments/{payment}/receipts/{transaction}
 *              behind the admin middleware, authorised by PaymentPolicy
 *
 *   visitor()  /receipt/{token}/{transaction}
 *              public, where the payment's 48-character token IS the credential
 *
 * Deliberately NOT two views. A receipt is a statement of fact about money, and
 * two templates would be two versions of that fact — the day someone corrects a
 * figure on one, the studio and the visitor are holding different documents. The
 * only difference is a `$staff` flag that adds an internal footnote, and that
 * flag can only ever ADD, never change or remove a figure.
 *
 * Lives in App\Http\Controllers rather than under Admin or Public because it is
 * genuinely both; putting it in either namespace would misdescribe half its job.
 */
class PayslipController extends Controller
{
    /**
     * Staff view, from the payments register.
     */
    public function staff(Payment $payment, PaymentTransaction $transaction): View
    {
        Gate::authorize('view', $payment);

        // The transaction is bound independently of the payment, so a URL
        // pairing someone else's receipt with a payment this user may see would
        // otherwise render. Checked rather than scoped so the failure is a 404
        // and not a silently different document.
        abort_unless($transaction->payment_id === $payment->id, 404);

        // PHASE 13. Only a settled attempt is a receipt. A failed or in-flight
        // one has no amount received, no balance and no moment — rendering a
        // payslip for it would produce a document asserting a payment that
        // never happened.
        abort_unless($transaction->isReceipt(), 404);

        return $this->render($payment, $transaction, staff: true);
    }

    /**
     * Visitor view. No login: the token in the URL is the credential.
     *
     * Bound on the token by hand rather than by route-model binding so that a
     * missing or wrong token is an ordinary 404 rather than a hint that the
     * reference existed. Nothing here reveals more than the visitor already has
     * in their own email.
     */
    public function visitor(Request $request, string $token, PaymentTransaction $transaction): View
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        abort_unless($transaction->payment_id === $payment->id, 404);
        abort_unless($transaction->isReceipt(), 404);

        return $this->render($payment, $transaction, staff: false);
    }

    private function render(Payment $payment, PaymentTransaction $transaction, bool $staff): View
    {
        /*
         | transactions.recordedBy is new: the payslip is a statement of the
         | whole request now and lists every receipt on it, so receipts() needs
         | the relation loaded or it reads an empty collection and the document
         | shows a total with nothing behind it.
         */
        $payment->load(['reservation.user', 'reservation.items', 'transactions.recordedBy']);
        $transaction->load('recordedBy');

        return view('payslips.show', [
            'payment'     => $payment,
            'transaction' => $transaction,
            'reservation' => $payment->reservation,
            'studio'      => config('shunno.contact'),
            'staff'       => $staff,
        ]);
    }
}