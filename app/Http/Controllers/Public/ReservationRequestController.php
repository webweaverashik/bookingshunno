<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Support\ExperienceCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ReservationRequestController extends Controller
{
    /**
     * Receive a reservation request from the popup.
     *
     * PHASE 4: this validates and prices correctly but does not persist yet —
     * there is no reservations table. Once there is, this method creates the
     * visitor (role Visitor, unusable password), the reservation and its items,
     * then fires ReservationRequested to send the acknowledgement email. The
     * response envelope below is already the shape the front end expects, so
     * the JavaScript will not need to change.
     */
    public function store(StoreReservationRequest $request): JsonResponse
    {
        $data       = $request->validated();
        $experience = collect(ExperienceCatalogue::all())->firstWhere('slug', $data['experience']);

        $pricing = $this->price((float) $experience['price'], (int) $data['participants']);

        // PHASE 4: replace with the persisted reservation's reference_code.
        $reference = 'SHN-' . now()->format('ym') . '-' . Str::upper(Str::random(4));

        return response()->json([
            'success' => true,
            'message' => 'Your request has been received.',
            'data'    => [
                'reference'  => $reference,
                'experience' => $experience['title'],
                'date'       => $data['date'],
                'time'       => $data['time'],
                'pricing'    => $pricing,
            ],
        ]);
    }

    /**
     * PHASE 4: moves to PricingService, which the admin panel will share.
     * Kept here so the popup's running total and the server agree today.
     */
    private function price(float $unitPrice, int $participants): array
    {
        $subtotal  = $unitPrice * $participants;
        $threshold = (int) config('shunno.group_discount.min_participants');
        $percent   = (int) config('shunno.group_discount.percentage');

        $discount = $participants >= $threshold
            ? round($subtotal * $percent / 100, 2)
            : 0.0;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total'    => $subtotal - $discount,
        ];
    }
}
