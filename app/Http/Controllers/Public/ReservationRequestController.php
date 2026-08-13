<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ReservationRequestController extends Controller
{
    public function __construct(private readonly PricingService $pricing)
    {
    }

    /**
     * Receive a reservation request from the popup.
     *
     * PHASE 6: the workshop and its price now come from the database rather
     * than ExperienceCatalogue, and pricing goes through PricingService so the
     * popup's running total, this response and the admin panel cannot disagree.
     *
     * STILL OUTSTANDING (Phase 4 closeout): this does not persist. The
     * reference below is generated and thrown away. ReservationService::
     * createFromPublicRequest() is written and does the whole job — creating
     * the visitor, the reservation, its line item, the purposes and the first
     * status-history row — but it is not wired here, and it writes two user
     * columns (total_reservations, last_reservation_at) that the users table
     * does not have yet. See the Phase 6 notes.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $data     = $request->validated();
        $workshop = $request->workshop();

        $pricing = $this->pricing->forWorkshop($workshop, (int) $data['participants']);

        $reference = 'SHN-' . now()->format('ym') . '-' . Str::upper(Str::random(4));

        return response()->json([
            'success' => true,
            'message' => 'Your request has been received.',
            'data'    => [
                'reference'  => $reference,
                'experience' => $workshop->title,
                'date'       => $data['date'],
                'time'       => $data['time'],
                'pricing'    => [
                    'subtotal' => $pricing['subtotal'],
                    'discount' => $pricing['discount'],
                    'total'    => $pricing['total'],
                ],
            ],
        ]);
    }
}
