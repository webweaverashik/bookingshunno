<?php

namespace App\Http\Controllers\Admin\Reservation\Concerns;

use App\Enums\Reservation\ReservationStatus;
use App\Models\Reservation\Reservation;
use App\Services\Availability\AvailabilityService;
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

        /*
         | An open-ended custom range is deliberately allowed. "Everything from
         | 1 June" and "everything up to today" are both real questions, and
         | insisting on both ends would make somebody type a date they do not
         | care about just to get past the form.
         */
        if ($filters['range'] === 'custom') {
            if ($filters['from'] !== '') {
                $query->whereDate('reserved_date', '>=', $filters['from']);
            }

            if ($filters['to'] !== '') {
                $query->whereDate('reserved_date', '<=', $filters['to']);
            }
        }

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
     * @return array{q:string,status:string,range:string,from:string,to:string,workshop:string,sort:string,dir:string,per_page:int}
     */
    protected function reservationFilters(Request $request): array
    {
        /*
         | DEFAULTS — any date, every status, on the client's instruction.
         |
         | The register used to open on "still open, today and ahead", which is
         | the right view for working the queue and the wrong one for the
         | commonest reason to come here at all: finding the booking somebody is
         | asking about on the phone, which is as likely to be last month's and
         | already cancelled. The four counters at the top of the page still
         | carry the queue, and the filter badge now says when a view has been
         | narrowed — which is what made the old default safe to drop.
         */
        $status   = (string) $request->query('status', 'all');
        $range    = (string) $request->query('range', 'all');
        $workshop = (string) $request->query('workshop', 'all');
        $sort     = (string) $request->query('sort', '');
        $dir      = strtolower((string) $request->query('dir', ''));
        $perPage  = (int) $request->query('per_page', 25);

        $allowedStatuses = array_merge(
            ['all', 'open', 'needs_decision'],
            array_column(ReservationStatus::cases(), 'value'),
        );

        $range = in_array($range, ['all', 'today', 'upcoming', 'past', 'custom'], true) ? $range : 'all';

        /*
         | Sorting follows the range, because the useful end of the list moves.
         |
         | A forward-looking range reads forwards, earliest first; history reads
         | backwards. "Any date" sorts on when the request ARRIVED rather than
         | on the visit date — the top of an unfiltered register should be what
         | happened most recently, not a booking eight months out. An explicit
         | choice from the user always wins over both.
         */
        $forward     = in_array($range, ['today', 'upcoming', 'custom'], true);
        $defaultDir  = $forward ? 'asc' : 'desc';
        $defaultSort = $range === 'all' ? 'created' : 'date';

        return [
            'q' => trim((string) $request->query('q', '')),

            // Whitelisted rather than passed through: each of these reaches a
            // query builder and a Blade selected() check.
            'status'   => in_array($status, $allowedStatuses, true) ? $status : 'all',
            'range'    => $range,
            'workshop' => ctype_digit($workshop) ? $workshop : 'all',

            // Shape-checked here. They are bound as values by the query
            // builder, so a malformed one is a wrong answer rather than a
            // danger — but a wrong answer nobody can see is worse than none.
            'from' => $this->reservationDate($request->query('from')),
            'to'   => $this->reservationDate($request->query('to')),

            'sort'     => array_key_exists($sort, self::SORTABLE) ? $sort : $defaultSort,
            'dir'      => in_array($dir, ['asc', 'desc'], true) ? $dir : $defaultDir,
            'per_page' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25,
        ];
    }

    /** A Y-m-d string, or empty. Anything else is not a date and is discarded. */
    private function reservationDate(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
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

            // PHASE 12A. The drawer's money block reads the payments COLLECTION
            // through Reservation::latestPayment() and amountPaid(), which
            // deliberately do not lazy-load — so it has to be eager-loaded here
            // or those helpers see nothing.
            'payments',
        ]);

        return view('admin.reservations.partials.detail', [
            'reservation'  => $reservation,
            'availability' => $this->availabilityVerdict($reservation),
            'completion'   => $this->completionVerdict($reservation),
        ])->render();
    }

    /**
     * Why the Complete button is not there yet.
     *
     * Only ever addressed to somebody who would otherwise be looking for it: a
     * user holding the permission, on a reservation sitting at Confirmed. For
     * everyone else it says nothing, because "you cannot complete this" is not
     * information to a Manager who never could.
     *
     * The reasoning lives here rather than in Blade because it reads an opening
     * hour and a balance, and both are business rules.
     *
     * @return array{pending:bool,reason:?string}
     */
    protected function completionVerdict(Reservation $reservation): array
    {
        $user = request()->user();
        $none = ['pending' => false, 'reason' => null];

        if (! $user?->can('reservations.complete') || $reservation->status !== ReservationStatus::Confirmed) {
            return $none;
        }

        // The button is on screen; there is nothing to explain.
        if ($user->can('complete', $reservation)) {
            return $none;
        }

        if (! $reservation->hasNothingLeftToPay()) {
            return [
                'pending' => true,
                'reason'  => 'BDT ' . number_format($reservation->outstandingTotal())
                    . ' is still outstanding. Record the balance in Payments and this becomes available.',
            ];
        }

        $opens = app(AvailabilityService::class)->window(
            CarbonImmutable::parse($reservation->reserved_date->toDateString())
        )['opens'] ?? null;

        return [
            'pending' => true,
            'reason'  => 'Available on ' . $reservation->reserved_date->format('j F')
                . ($opens ? ', from ' . $opens->format('g:i A') . ' when the studio opens.' : '.'),
        ];
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

            // Or a reservation that holds capacity fails its own check: its own
            // people are already counted in the slot it is sitting in.
            except: $reservation,
        );

        return [
            'checked' => true,
            'ok'      => $result['ok'],
            'reason'  => $result['reason'],
        ];
    }
}
