<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Workshop;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only. The popup calls this whenever the visitor changes the session or
 * the date, so the time list reflects the studio's actual hours, holidays and
 * — once capacity is switched on — seats already taken.
 *
 * Nothing here decides anything: StoreReservationRequest re-runs the same
 * service on submit. This endpoint exists so the visitor is not told "no"
 * only after filling in the whole form.
 */
class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $workshop = Workshop::query()
            ->active()
            ->where('slug', (string) $request->query('experience'))
            ->first();

        if (! $workshop) {
            return response()->json([
                'success' => false,
                'message' => 'That session is not currently available.',
            ], 404);
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query('date'))->startOfDay();
        } catch (InvalidFormatException) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose a valid date.',
            ], 422);
        }

        $problem = $this->availability->dateProblem($date);

        return response()->json([
            'success' => true,
            'data'    => [
                'date'     => $date->toDateString(),
                'open'     => $problem === null,
                'message'  => $problem,
                'duration' => $workshop->durationLabel(),
                'slots'    => $problem === null
                    ? $this->availability->slotsFor($workshop, $date)
                    : [],
            ],
        ]);
    }
}
