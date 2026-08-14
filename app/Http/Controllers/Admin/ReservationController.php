<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * PHASE 9 — the reservation register.
 *
 * List, search, filter, view, edit, history. Deliberately NOT approve, decline
 * or request-more-information: those are Phase 10, they each carry an email in
 * Phase 11, and shipping a button now that changes a status without notifying
 * anybody is worse than shipping no button.
 *
 * Same shape as Phase 8's visitors: filters go up as query parameters, the
 * rendered list comes back as HTML inside the standard envelope, and the
 * JavaScript swaps one container. Nothing in the admin panel builds markup in
 * the browser.
 */
class ReservationController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly AvailabilityService $availability,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Reservation::class);

        return view('admin.reservations.index', [
            'reservations' => $this->query($request),
            'filters'      => $this->filters($request),
            'stats'        => $this->stats(),
            'workshops'    => Workshop::query()->orderBy('title')->get(['id', 'title']),
            'statuses'     => ReservationStatus::cases(),
        ]);
    }

    /** List container only — used for search, filtering and paging. */
    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Reservation::class);

        return $this->listResponse($request);
    }

    /**
     * The full record: visit, visitor, money, purposes and the whole status
     * history. Rendered server-side and dropped into the drawer so the badges,
     * money and dates keep the formatting the rest of the panel uses.
     */
    public function show(Request $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('view', $reservation);

        $reservation->load([
            'user',
            'items.workshop',
            'purposes',
            'statusHistory.changedBy',
            'approver',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'html'      => view('admin.reservations.partials.detail', [
                    'reservation' => $reservation,
                ])->render(),
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

        $this->reservations->amend(
            $reservation,
            $changes,
            $request->user(),
            $data['note'] ?? null,
            $request->wantsOverride(),
        );

        return $this->listResponse(
            $request,
            "{$reservation->reference_code} has been updated."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function query(Request $request): LengthAwarePaginator
    {
        $filters = $this->filters($request);
        $today   = CarbonImmutable::today()->toDateString();

        $query = Reservation::query()
            ->with(['user:id,name,email,phone', 'items:id,reservation_id,title_snapshot,workshop_id'])
            ->search($filters['q']);

        // Status: 'open' is the one staff want most often — everything still
        // waiting on somebody — so it gets a name of its own rather than
        // forcing four separate selections.
        if ($filters['status'] === 'open') {
            $query->open();
        } elseif ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['workshop'] !== 'all') {
            $query->whereHas('items', fn ($q) => $q->where('workshop_id', $filters['workshop']));
        }

        match ($filters['range']) {
            'today'    => $query->whereDate('reserved_date', $today),
            'upcoming' => $query->whereDate('reserved_date', '>=', $today),
            'past'     => $query->whereDate('reserved_date', '<', $today),
            default    => null,
        };

        // Upcoming work reads forwards — the next visit first. History reads
        // backwards. Sorting both the same way would put the useful end of one
        // of them on the last page.
        $ascending = in_array($filters['range'], ['today', 'upcoming'], true);

        return $query
            ->orderBy('reserved_date', $ascending ? 'asc' : 'desc')
            ->orderBy('start_time', $ascending ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** @return array{q:string,status:string,range:string,workshop:string} */
    private function filters(Request $request): array
    {
        $status   = (string) $request->query('status', 'open');
        $range    = (string) $request->query('range', 'upcoming');
        $workshop = (string) $request->query('workshop', 'all');

        $allowedStatuses = array_merge(
            ['all', 'open'],
            array_column(ReservationStatus::cases(), 'value'),
        );

        return [
            'q' => trim((string) $request->query('q', '')),

            // Whitelisted rather than passed through: each of these reaches a
            // query builder and a Blade selected() check.
            'status' => in_array($status, $allowedStatuses, true) ? $status : 'open',
            'range'  => in_array($range, ['all', 'today', 'upcoming', 'past'], true) ? $range : 'upcoming',

            'workshop' => ctype_digit($workshop) ? $workshop : 'all',
        ];
    }

    /**
     * The four numbers worth having above the list. Anything more belongs in
     * Phase 16's reports.
     */
    private function stats(): array
    {
        $today = CarbonImmutable::today()->toDateString();

        return [
            'pending' => Reservation::query()->awaitingReview()->count(),

            'awaitingPayment' => Reservation::query()->whereIn('status', [
                ReservationStatus::Approved->value,
                ReservationStatus::PaymentRequested->value,
            ])->count(),

            'upcoming' => Reservation::query()
                ->where('status', ReservationStatus::Confirmed)
                ->whereDate('reserved_date', '>=', $today)
                ->count(),

            'thisMonth' => Reservation::query()
                ->where('created_at', '>=', now()->startOfMonth())
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

    private function listResponse(Request $request, ?string $message = null): JsonResponse
    {
        $reservations = $this->query($request);

        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'data'    => [
                'html'  => view('admin.reservations.partials.list', [
                    'reservations' => $reservations,
                    'filters'      => $this->filters($request),
                ])->render(),
                'total' => $reservations->total(),
            ],
        ], fn ($value) => $value !== null));
    }
}
