<?php

namespace App\Http\Controllers\Admin\Reservation;

use App\Enums\Reservation\ReservationStatus;
use App\Http\Controllers\Admin\Reservation\Concerns\RendersReservations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reservation\ReservationDecisionRequest;
use App\Models\Reservation\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * The approval workflow.
 *
 * Separate from ReservationController on purpose: that one reads and corrects,
 * this one decides. They share the list and drawer rendering through
 * RendersReservations rather than by living in one 500-line class.
 *
 * Every method does the same four things — authorise, transition, record,
 * re-render — so the shape is in decide() and the six public methods are the
 * six things that differ.
 *
 * NOT YET WIRED: none of these send an email. Phase 11 hangs the notifications
 * off the transitions ReservationService already records. Escalation is the one
 * that hurts most in the meantime — a Manager escalating expects an Admin to
 * find out — so until Phase 11 lands, the Escalated filter in the register is
 * the only signal, and the drawer says so.
 */
class ReservationDecisionController extends Controller
{
    use RendersReservations;

    public function __construct(private readonly ReservationService $reservations)
    {
    }

    public function approve(ReservationDecisionRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->decide(
            $request,
            $reservation,
            ReservationStatus::Approved,
            'approve',
            "{$reservation->reference_code} has been approved.",
        );
    }

    /**
     * PHASE 10A — hand the decision to an Admin.
     *
     * A Manager may prepare a request — fix the party size, move the date — but
     * not commit the studio to it. This is how they ask.
     */
    public function escalate(ReservationDecisionRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->decide(
            $request,
            $reservation,
            ReservationStatus::Escalated,
            'escalate',
            "{$reservation->reference_code} has been escalated. An Admin will need to decide it.",
        );
    }

    public function decline(ReservationDecisionRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->decide(
            $request,
            $reservation,
            ReservationStatus::Declined,
            'decline',
            "{$reservation->reference_code} has been declined.",
        );
    }

    public function requestInfo(ReservationDecisionRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->decide(
            $request,
            $reservation,
            ReservationStatus::InfoRequested,
            'requestInfo',
            "{$reservation->reference_code} is now waiting on the visitor.",
        );
    }

    public function returnToReview(ReservationDecisionRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->decide(
            $request,
            $reservation,
            ReservationStatus::Pending,
            'returnToReview',
            "{$reservation->reference_code} is back in the review queue.",
        );
    }

    public function cancel(ReservationDecisionRequest $request, Reservation $reservation): JsonResponse
    {
        return $this->decide(
            $request,
            $reservation,
            ReservationStatus::Cancelled,
            'cancel',
            "{$reservation->reference_code} has been cancelled.",
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function decide(
        ReservationDecisionRequest $request,
        Reservation $reservation,
        ReservationStatus $to,
        string $ability,
        string $message,
    ): JsonResponse {
        // The policy asks both questions: does this person hold the permission,
        // and does the lifecycle allow this move from here. A stale drawer left
        // open while somebody else decided the same request fails here.
        Gate::authorize($ability, $reservation);

        $note = $request->validated()['note'] ?? null;

        if ($request->wantsOverride() && $to === ReservationStatus::Approved) {
            $note = trim(($note ? $note . ' ' : '') . 'Approved despite the slot being unavailable.');
        }

        try {
            $this->reservations->transition($reservation, $to, $request->user(), $note);
        } catch (RuntimeException $e) {
            // The enum refused the move. Almost always means two people were
            // looking at the same request; the message names the actual
            // sequence rather than saying "failed".
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() . ' It may have been decided by someone else — reopen it to see where it stands.',
            ], 409);
        }

        // PHASE 11: the notification for this transition goes out here, from an
        // event the service raises rather than from this controller. Escalated
        // is the one that needs to reach staff rather than the visitor.

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'list'   => $this->reservationListPayload($request),
                'detail' => $this->reservationDetailHtml($reservation->refresh()),
            ],
        ]);
    }
}
