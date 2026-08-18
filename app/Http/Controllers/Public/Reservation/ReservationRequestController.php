<?php

namespace App\Http\Controllers\Public\Reservation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreReservationRequest;
use App\Services\Reservation\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReservationRequestController extends Controller
{
    public function __construct(private readonly ReservationService $reservations)
    {
    }

    /**
     * Receive a reservation request from the popup and persist it.
     *
     * PHASE 4 CLOSEOUT: this used to generate a reference, return it, and throw
     * everything away. ReservationService does the whole job in one
     * transaction — visitor, reservation, line item, purposes, first status
     * history row — and it is the only place a reservation is ever created, so
     * an admin-entered booking in Phase 9 goes through the same code.
     *
     * Nothing is emailed yet; Phase 11 hangs the acknowledgement off the event
     * the service already marks out.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $reservation = $this->reservations->createFromPublicRequest(
                $data,
                $request->ip(),
            );
        } catch (Throwable $e) {
            // The visitor gets nothing technical; §16 forbids leaking the
            // exception. The reference is what support would need to trace it.
            Log::error('Reservation request failed', [
                'exception'  => $e,
                'experience' => $data['experience'] ?? null,
                'date'       => $data['date'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong saving your request. Please try again, or message us on WhatsApp and we will take it from there.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Your request has been received.',
            'data'    => [
                'reference'  => $reservation->reference_code,
                'experience' => $reservation->items->first()?->title_snapshot,
                'date'       => $reservation->reserved_date->toDateString(),
                'time'       => substr((string) $reservation->start_time, 0, 5),
                'pricing'    => [
                    'subtotal' => (float) $reservation->subtotal,
                    'discount' => (float) $reservation->discount_amount,
                    'total'    => (float) $reservation->total_amount,
                ],
            ],
        ]);
    }
}
