<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\CommunicationLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * PHASE 13B — the message history, and resending.
 *
 * Two list endpoints rather than one generic one, because the two callers ask
 * different questions. A reservation drawer wants everything ever sent about
 * this booking; a payment drawer wants only what concerns this request. Merging
 * them into a filtered endpoint would mean the payment drawer had to know how
 * to exclude approval emails, which is not its business.
 */
class CommunicationController extends Controller
{
    public function __construct(private readonly CommunicationLogger $log)
    {
    }

    /** Every message about a reservation. */
    public function forReservation(Reservation $reservation): JsonResponse
    {
        Gate::authorize('view', $reservation);

        $messages = Communication::with(['triggeredBy:id,name'])
            ->where('reservation_id', $reservation->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return $this->render($messages);
    }

    /** Only the messages about one payment request. */
    public function forPayment(Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        $messages = Communication::with(['triggeredBy:id,name'])
            ->where('payment_id', $payment->id)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return $this->render($messages);
    }

    public function resend(Communication $communication): JsonResponse
    {
        Gate::authorize('resend', $communication);

        try {
            $sent = $this->log->resend($communication, request()->user());
        } catch (RuntimeException $e) {
            // Throttled, or the underlying payment has since been withdrawn.
            // 409 rather than 422 — nothing is wrong with the request, the
            // world simply is not in a state where it can be honoured.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => "Sent again to {$sent->to_email}.",
            'data'    => ['html' => $this->listHtml(
                Communication::with('triggeredBy:id,name')
                    ->where('reservation_id', $sent->reservation_id)
                    ->when($communication->payment_id, fn ($q) => $q->where('payment_id', $communication->payment_id))
                    ->latest('created_at')
                    ->limit(50)
                    ->get()
            )],
        ]);
    }

    private function render($messages): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['html' => $this->listHtml($messages)],
        ]);
    }

    private function listHtml($messages): string
    {
        return view('admin.partials.messages', ['messages' => $messages])->render();
    }
}
