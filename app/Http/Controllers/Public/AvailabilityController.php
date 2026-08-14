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
 * Read-only. The popup calls these whenever the visitor changes the session,
 * the month or the date, so both the calendar and the time list reflect the
 * studio's actual hours, holidays and — once capacity is switched on — seats
 * already taken.
 *
 * Nothing here decides anything: StoreReservationRequest re-runs the same
 * service on submit. These endpoints exist so the visitor is not told "no"
 * only after filling in the whole form.
 *
 * PHASE 7C:
 *   - split into slots() and calendar(); the route names are unchanged for the
 *     first and `availability.calendar` for the second;
 *   - both send no-store, because a cached month is a month that keeps showing
 *     a date the admin blocked an hour ago;
 *   - the slots payload now carries the group-size limits, so the participants
 *     field can be bounded by the session the visitor actually picked.
 */
class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    /** Start times for one session on one date. */
    public function slots(Request $request): JsonResponse
    {
        $workshop = $this->workshop($request);

        if (! $workshop) {
            return $this->missingWorkshop();
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('date'));
        } catch (InvalidFormatException) {
            return $this->json([
                'success' => false,
                'message' => 'Please choose a valid date.',
            ], 422);
        }

        $problem = $this->availability->dateProblem($date);

        return $this->json([
            'success' => true,
            'data'    => [
                'date'     => $date->toDateString(),
                'open'     => $problem === null,
                'message'  => $problem,
                'duration' => $workshop->durationLabel(),
                'limits'   => $this->availability->participantLimits($workshop),
                'slots'    => $problem === null
                    ? $this->availability->slotsFor($workshop, $date)
                    : [],
            ],
        ]);
    }

    /**
     * One month of days for the date picker, each marked bookable or not.
     *
     * The month is clamped into the bookable window rather than rejected: a
     * visitor who arrives from a stale link asking for last March should see
     * the first month they can actually book, not an error.
     */
    public function calendar(Request $request): JsonResponse
    {
        $workshop = $this->workshop($request);

        if (! $workshop) {
            return $this->missingWorkshop();
        }

        $requested = (string) $request->query('month', '');

        try {
            $month = $requested === ''
                ? CarbonImmutable::today()->startOfMonth()
                : CarbonImmutable::createFromFormat('!Y-m', $requested)->startOfMonth();
        } catch (InvalidFormatException) {
            $month = CarbonImmutable::today()->startOfMonth();
        }

        $month = $this->availability->clampMonth($month);

        return $this->json([
            'success' => true,
            'data'    => $this->availability->calendar($workshop, $month) + [
                'limits'   => $this->availability->participantLimits($workshop),
                'duration' => $workshop->durationLabel(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function workshop(Request $request): ?Workshop
    {
        return Workshop::query()
            ->active()
            ->where('slug', (string) $request->query('experience'))
            ->first();
    }

    private function missingWorkshop(): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => 'That session is not currently available.',
        ], 404);
    }

    /**
     * Availability is live data. Without this a browser is free to reuse an
     * earlier answer heuristically, which looks exactly like the admin's
     * changes to the opening hours having no effect.
     */
    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
