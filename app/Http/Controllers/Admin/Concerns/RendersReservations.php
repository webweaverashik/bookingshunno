<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared by ReservationController and ReservationDecisionController.
 *
 * The decision endpoints have to return the refreshed list and the refreshed
 * drawer, which means they need the same filter parsing, the same query and the
 * same rendering as the register itself. Copying any of it would give the two
 * controllers separate definitions of what "still open, today and ahead" means,
 * and they would drift the first time a filter changed.
 *
 * PHASE 10A adds sorting and a page-size choice. Both are server-side, for the
 * same reason the paging always was: the table is expected to grow, and a
 * client-side table has to hold every row in the browser before it can sort
 * one. See the note on SORTABLE below.
 */
trait RendersReservations
{
    /**
     * Sortable columns, whitelisted.
     *
     * The key is what appears in the URL; the value is the real column. A user
     * -supplied string never reaches orderBy() — that is a SQL injection route
     * that validation on the rest of the request would not catch, because the
     * column name is not a value and cannot be bound as one.
     */
    private const SORTABLE = [
        'reference' => 'reference_code',
        'date'      => 'reserved_date',
        'people'    => 'participants',
        'total'     => 'total_amount',
        'status'    => 'status',
        'created'   => 'created_at',
    ];

    private const PAGE_SIZES = [25, 50, 100];

    protected function reservationQuery(Request $request): LengthAwarePaginator
    {
        $filters = $this->reservationFilters($request);
        $today   = CarbonImmutable::today()->toDateString();

        $query = Reservation::query()
            ->with(['user:id,name,email,phone', 'items:id,reservation_id,title_snapshot,workshop_id'])
            ->search($filters['q']);

        // 'open' is the one staff want most often — everything still waiting on
        // somebody — so it gets a name of its own rather than forcing five
        // separate selections. 'needs_decision' is narrower: waiting on US, not
        // on the visitor or on a payment.
        match ($filters['status']) {
            'open'           => $query->open(),
            'needs_decision' => $query->needingDecision(),
            'all'            => null,
            default          => $query->where('status', $filters['status']),
        };

        if ($filters['workshop'] !== 'all') {
            $query->whereHas('items', fn ($q) => $q->where('workshop_id', $filters['workshop']));
        }

        match ($filters['range']) {
            'today'    => $query->whereDate('reserved_date', $today),
            'upcoming' => $query->whereDate('reserved_date', '>=', $today),
            'past'     => $query->whereDate('reserved_date', '<', $today),
            default    => null,
        };

        $column = self::SORTABLE[$filters['sort']];

        $query->orderBy($column, $filters['dir']);

        // Two reservations on the same date need a stable second key, or the
        // same query returns them in a different order on page 2 than page 1
        // and a row can appear twice or not at all.
        if ($column === 'reserved_date') {
            $query->orderBy('start_time', $filters['dir']);
        }

        return $query
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /**
     * @return array{q:string,status:string,range:string,workshop:string,sort:string,dir:string,per_page:int}
     */
    protected function reservationFilters(Request $request): array
    {
        $status   = (string) $request->query('status', 'open');
        $range    = (string) $request->query('range', 'upcoming');
        $workshop = (string) $request->query('workshop', 'all');
        $sort     = (string) $request->query('sort', '');
        $dir      = strtolower((string) $request->query('dir', ''));
        $perPage  = (int) $request->query('per_page', 25);

        $allowedStatuses = array_merge(
            ['all', 'open', 'needs_decision'],
            array_column(ReservationStatus::cases(), 'value'),
        );

        $range = in_array($range, ['all', 'today', 'upcoming', 'past'], true) ? $range : 'upcoming';

        // Upcoming work reads forwards — the next visit first. History reads
        // backwards. Sorting both the same way would put the useful end of one
        // of them on the last page, so the DEFAULT direction depends on the
        // range; an explicit choice from the user always wins.
        $defaultDir = in_array($range, ['today', 'upcoming'], true) ? 'asc' : 'desc';

        return [
            'q' => trim((string) $request->query('q', '')),

            // Whitelisted rather than passed through: each of these reaches a
            // query builder and a Blade selected() check.
            'status'   => in_array($status, $allowedStatuses, true) ? $status : 'open',
            'range'    => $range,
            'workshop' => ctype_digit($workshop) ? $workshop : 'all',

            'sort'     => array_key_exists($sort, self::SORTABLE) ? $sort : 'date',
            'dir'      => in_array($dir, ['asc', 'desc'], true) ? $dir : $defaultDir,
            'per_page' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25,
        ];
    }

    /** @return array<int,int> */
    protected function reservationPageSizes(): array
    {
        return self::PAGE_SIZES;
    }

    /** @return array{html:string,total:int} */
    protected function reservationListPayload(Request $request): array
    {
        $reservations = $this->reservationQuery($request);

        return [
            'html' => view('admin.reservations.partials.list', [
                'reservations' => $reservations,
                'filters'      => $this->reservationFilters($request),
            ])->render(),
            'total' => $reservations->total(),
        ];
    }

    /**
     * The drawer, including the live availability verdict.
     *
     * The verdict is computed at render time rather than stored: a request that
     * was fine when it arrived on Monday may be sitting on a slot that filled
     * up on Tuesday, and the whole point of showing it is that the admin sees
     * the situation now, not when the visitor submitted.
     */
    protected function reservationDetailHtml(Reservation $reservation): string
    {
        $reservation->load([
            'user',
            'items.workshop',
            'purposes',
            'statusHistory.changedBy',
            'approver',
        ]);

        return view('admin.reservations.partials.detail', [
            'reservation'  => $reservation,
            'availability' => $this->availabilityVerdict($reservation),
        ])->render();
    }

    /**
     * Whether this reservation could still be placed where it sits.
     *
     * Only asked while it is undecided — re-checking a declined request tells
     * nobody anything, and re-checking a confirmed one would flag the
     * reservation's own seats as a conflict with itself.
     *
     * @return array{checked:bool,ok:bool,reason:?string}
     */
    protected function availabilityVerdict(Reservation $reservation): array
    {
        $undecided = in_array($reservation->status, ReservationStatus::needingDecision(), true);
        $workshop  = $reservation->workshop();

        if (! $undecided || ! $workshop) {
            return ['checked' => false, 'ok' => true, 'reason' => null];
        }

        $result = app(AvailabilityService::class)->check(
            $workshop,
            $reservation->reserved_date->toDateString(),
            substr((string) $reservation->start_time, 0, 5),
            (int) $reservation->participants,
        );

        return [
            'checked' => true,
            'ok'      => $result['ok'],
            'reason'  => $result['reason'],
        ];
    }
}
