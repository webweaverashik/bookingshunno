<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Admin\Concerns\RendersReservations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReservationEditRequest;
use App\Models\Reservation;
use App\Models\Workshop;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The reservation register. List, search, filter, sort, view, edit, history.
 *
 * Decisions live in ReservationDecisionController; the list and drawer
 * rendering lives in the RendersReservations trait, which both use. This class
 * no longer knows how a filter is parsed; it asks.
 *
 * Filters go up as query parameters, the rendered list comes back as HTML
 * inside the standard envelope, and the JavaScript swaps one container.
 * Nothing in the admin panel builds markup in the browser.
 */
class ReservationController extends Controller
{
    use RendersReservations;

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly AvailabilityService $availability,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Reservation::class);

        return view('admin.reservations.index', [
            'reservations' => $this->reservationQuery($request),
            'filters'      => $this->reservationFilters($request),
            'pageSizes'    => $this->reservationPageSizes(),
            'stats'        => $this->stats(),
            'workshops'    => Workshop::query()->orderBy('title')->get(['id', 'title']),
            'statuses'     => ReservationStatus::cases(),
        ]);
    }

    /** List container only — used for search, filtering, sorting and paging. */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Reservation::class);

        return response()->json([
            'success' => true,
            'data'    => $this->reservationListPayload($request),
        ]);
    }

    /**
     * The full record: visit, visitor, money, purposes, the whole status
     * history, and what may be done to it next.
     */
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('view', $reservation);

        return response()->json([
            'success' => true,
            'data'    => [
                'html'      => $this->reservationDetailHtml($reservation),
                'reference' => $reservation->reference_code,
            ],
        ]);
    }

    /**
     * The edit form, rendered whole rather than filled field by field.
     *
     * The time select's options depend on the date, the session's duration and
     * the availability rules — building those in JavaScript would put slot
     * logic in the browser, which §17 and Phase 7A both rule out.
     */
    public function edit(Reservation $reservation): JsonResponse
    {
        Gate::authorize('update', $reservation);

        $reservation->load(['user', 'items.workshop']);

        return response()->json([
            'success' => true,
            'data'    => [
                'html'       => view('admin.reservations.partials.edit-form', [
                    'reservation' => $reservation,
                    'slots'       => $this->slotsFor($reservation, $reservation->reserved_date->toDateString()),
                    'canOverride' => Gate::allows('overrideAvailability', $reservation),
                    'canSetPrice' => Gate::allows('setPrice', $reservation),
                ])->render(),
                'update_url' => route('admin.reservations.update', $reservation),
                'reference'  => $reservation->reference_code,
                'editable'   => $reservation->isEditable(),
            ],
        ]);
    }

    /**
     * Re-rendered <option> list for a new date, for the same reason as above.
     */
    public function slots(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('update', $reservation);

        $date = (string) $request->query('date', '');

        return response()->json([
            'success' => true,
            'data'    => [
                'html' => view('admin.reservations.partials.slot-options', [
                    'slots'    => $this->slotsFor($reservation, $date),
                    'selected' => substr((string) $reservation->start_time, 0, 5),
                ])->render(),
            ],
        ]);
    }

    public function update(ReservationEditRequest $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('update', $reservation);

        $data = $request->validated();

        // Notes always; the visit fields only while the reservation is still
        // ahead of a payment request. A payload carrying a new date for a
        // confirmed booking is dropped here rather than half-applied.
        $changes = ['special_requests' => $data['special_requests'] ?? null];

        if ($reservation->isEditable()) {
            $changes += [
                'reserved_date' => $data['reserved_date'],
                'start_time'    => $data['start_time'],
                'participants'  => (int) $data['participants'],
            ];
        }

        // Empty for anyone who may not set a price, so the columns are not
        // touched — which is different from being set to null.
        $changes += $request->priceChanges();

        $this->reservations->amend(
            $reservation,
            $changes,
            $request->user(),
            $data['note'] ?? null,
            $request->wantsOverride(),
        );

        return response()->json([
            'success' => true,
            'message' => "{$reservation->reference_code} has been updated.",
            'data'    => [
                'list'   => $this->reservationListPayload($request),
                'detail' => $this->reservationDetailHtml($reservation->refresh()),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The four numbers worth having above the list. Anything richer belongs in
     * Phase 16's reports.
     */
    private function stats(): array
    {
        $today = CarbonImmutable::today()->toDateString();

        return [
            // Everything waiting on somebody here, escalations included — a
            // Manager's escalation must not vanish from the count that tells
            // the studio how much is outstanding.
            'pending' => Reservation::query()->needingDecision()->count(),

            'escalated' => Reservation::query()
                ->where('status', ReservationStatus::Escalated)
                ->count(),

            'awaitingPayment' => Reservation::query()->whereIn('status', [
                ReservationStatus::Approved->value,
                ReservationStatus::PaymentRequested->value,
            ])->count(),

            'upcoming' => Reservation::query()
                ->where('status', ReservationStatus::Confirmed)
                ->whereDate('reserved_date', '>=', $today)
                ->count(),
        ];
    }

    /**
     * Slots for a date, using the workshop this reservation was booked against.
     *
     * @return array<int,array<string,mixed>>
     */
    private function slotsFor(Reservation $reservation, string $date): array
    {
        $workshop = $reservation->workshop();

        if (! $workshop || $date === '') {
            return [];
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            return [];
        }

        return $this->availability->slotsFor($workshop, $day);
    }
}
