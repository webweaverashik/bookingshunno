<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Visitor\UpdateVisitorProfileRequest;
use App\Models\Reservation\Reservation;
use App\Services\Visitor\VisitorPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PHASE 15 — the visitor's own pages.
 *
 * Read-mostly by design. A visitor can look at their bookings, follow a
 * payment link they already had, read their voucher codes, and correct their
 * own phone number. They cannot cancel, reschedule, or change a party size,
 * because every one of those moves money or capacity and §8 of the brief puts
 * both behind a person at the studio. A "cancel my booking" button would be one
 * click from a refund question nobody has answered yet.
 */
class VisitorAreaController extends Controller
{
    public function __construct(private readonly VisitorPortalService $portal) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $visits = $this->portal->visits($user);
        $vouchers = $this->portal->vouchers($user);

        return view('visitor.index', [
            'upcoming' => $visits['upcoming'],
            'past' => $visits['past'],
            'vouchers' => $vouchers,
            'summary' => $this->portal->summary($user, $vouchers),
            'portal' => $this->portal,
        ]);
    }

    /**
     * One booking.
     *
     * Bound on reference_code, which is Reservation's route key — the same code
     * that appears in every email, so a visitor can paste it and land here.
     *
     * A booking belonging to somebody else is a 404, not a 403. "You are not
     * allowed to see this" confirms the reference exists, and references are
     * short enough to be guessed at; "no such thing" says nothing either way.
     */
    public function show(Request $request, Reservation $reservation): View
    {
        abort_unless($reservation->user_id === $request->user()->id, 404);

        $reservation->load([
            'items.workshop',
            'payments.transactions',
            'purposes',
        ]);

        return view('visitor.show', [
            'reservation' => $reservation,
            'next' => $this->portal->nextStep($reservation),

            // Credit earned BY this visit. Distinct from the vouchers list on
            // the index, which is everything the person holds.
            'vouchers' => $this->portal->creditFrom($reservation),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Contact details
    |--------------------------------------------------------------------------
    */

    public function account(Request $request): View
    {
        return view('visitor.account', ['user' => $request->user()]);
    }

    /**
     * Name, phone and WhatsApp. Not email.
     *
     * The address is the account: it is what resolveVisitor() files
     * reservations under, what every notification has already gone to, and the
     * only credential this side of the app has. Letting it be edited from a
     * signed-in session would mean a borrowed laptop could quietly move
     * somebody's booking history to a new inbox. Changing it is a conversation
     * with the studio.
     *
     * A plain POST with a flash, not AJAX. §14's AJAX requirement is about
     * admin CRUD; the public side — the reservation popup aside — posts and
     * redirects, which is what the payment portal does and what works without
     * JavaScript.
     */
    public function updateAccount(UpdateVisitorProfileRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return redirect()->route('visitor.account')
            ->with('visitor_notice', 'Your details are updated.');
    }
}
