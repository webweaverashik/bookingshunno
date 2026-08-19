<?php

namespace App\Services\Availability;

use App\Models\Availability\BlockedDate;
use App\Models\Availability\OperatingHour;
use App\Models\Reservation\Reservation;
use App\Models\Workshop\Workshop;
use App\Services\Setting\SettingsRepository;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The single authority on whether a given session can be booked at a given time.
 *
 * Replaces App\Support\Availability\SessionSlots entirely. SessionSlots read the operating
 * window from config, so the hours the client can already edit in the database
 * had no effect on anything. It also knew nothing about blocked dates or seats
 * already taken.
 *
 * Every answer here is derived server-side. The reservation popup calls
 * slotsFor() through /availability and calendar() through /availability/calendar
 * purely so the visitor sees a sensible list; StoreReservationRequest calls
 * check() again on submit and that second call is the one that decides. Nothing
 * the browser sends is trusted (§19).
 *
 * Ordering of the rules matters: the cheapest and most decisive checks run
 * first, so a request for a Sunday never reaches a capacity query.
 *
 * PHASE 7C adds three things:
 *   - calendar(), so the date field can grey out days instead of accepting one
 *     and then explaining itself;
 *   - participantLimits() and a group-size rule in check(), so a session's own
 *     maximum is enforced rather than only the site-wide ceiling of 30;
 *   - a per-request block cache, because calendar() would otherwise run two
 *     queries per slot per day.
 */
class AvailabilityService
{
    /** @var array<int,OperatingHour>|null */
    private static ?array $hours = null;

    /**
     * Blocked dates, loaded a calendar month at a time and grouped by date.
     *
     * Instance-scoped, so it lives exactly as long as one request. A month is
     * the right unit: the calendar endpoint asks for one, and a single-date
     * lookup that pulls its whole month costs the same one query.
     *
     * @var array<string,Collection<string,Collection<int,BlockedDate>>>
     */
    private array $blocks = [];

    public function __construct(private readonly SettingsRepository $settings) {}

    /*
    |--------------------------------------------------------------------------
    | The operating window
    |--------------------------------------------------------------------------
    */

    /**
     * Opening and closing time for the weekday a date falls on, or null when
     * the studio is closed that day.
     *
     * @return array{opens: CarbonImmutable, closes: CarbonImmutable}|null
     */
    public function window(CarbonImmutable $date): ?array
    {
        $hours = $this->hoursFor($date->dayOfWeek);

        if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
            return null;
        }

        return [
            'opens' => $date->setTimeFromTimeString($hours->opens_at),
            'closes' => $date->setTimeFromTimeString($hours->closes_at),
        ];
    }

    /**
     * Has the studio's opening hour on this date already passed?
     *
     * Gates the Complete button. The client asked for the studio's opening
     * time rather than the session's start, which is the more forgiving of the
     * two and the right one: staff mark visits off during the day, not at the
     * minute each session begins.
     *
     * Falls back to midnight when the studio is closed that weekday. There are
     * reservations on closed days — an Admin can override availability to place
     * one — and refusing to ever complete them would leave those bookings
     * stranded at Confirmed for good.
     */
    public function hasOpenedOn(string|CarbonInterface $date, ?CarbonImmutable $now = null): bool
    {
        $day = $date instanceof CarbonInterface ? CarbonImmutable::parse($date->toDateString()) : CarbonImmutable::parse($date)->startOfDay();

        $opens = $this->window($day)['opens'] ?? $day->startOfDay();

        return ($now ?? CarbonImmutable::now())->greaterThanOrEqualTo($opens);
    }

    /**
     * Longest session the studio could ever run, in minutes. Drives the
     * duration ceiling in the workshop admin form: a session longer than the
     * widest weekday window could never be scheduled at all.
     */
    public function longestWindowMinutes(): int
    {
        $longest = 0;

        foreach ($this->allHours() as $hours) {
            if ($hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
                continue;
            }

            $opens = CarbonImmutable::createFromTimeString($hours->opens_at);
            $closes = CarbonImmutable::createFromTimeString($hours->closes_at);

            $longest = max($longest, (int) $opens->diffInMinutes($closes));
        }

        // Falls back to the shipped 16:00–21:30 window if the table is empty,
        // so a half-seeded database cannot make every duration invalid.
        return $longest ?: 330;
    }

    /**
     * The open days and the widest window across them, for the copy under the
     * date field. Previously that copy was hard-coded to "Monday to Saturday,
     * closed Sundays", which stopped being true the moment the client edited
     * the hours in the Phase 7B screen.
     *
     * @return array{open_days:array<int,int>,closed_days:array<int,int>,opens_at:?string,closes_at:?string}
     */
    public function weekSummary(): array
    {
        $open = [];
        $closed = [];
        $opensAt = null;
        $closesAt = null;

        foreach (range(0, 6) as $day) {
            $hours = $this->hoursFor($day);

            if (! $hours || $hours->is_closed || ! $hours->opens_at || ! $hours->closes_at) {
                $closed[] = $day;

                continue;
            }

            $open[] = $day;

            $opensAt = $opensAt === null ? $hours->opens_at : min($opensAt, $hours->opens_at);
            $closesAt = $closesAt === null ? $hours->closes_at : max($closesAt, $hours->closes_at);
        }

        return [
            'open_days' => $open,
            'closed_days' => $closed,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Date-level rules
    |--------------------------------------------------------------------------
    */

    /**
     * Why this date cannot be booked at all, or null when it can.
     *
     * Returns the reason rather than a boolean so the popup and the validator
     * can show the visitor the actual problem instead of a generic refusal.
     */
    public function dateProblem(CarbonImmutable $date): ?string
    {
        $today = CarbonImmutable::today();

        if ($date->lessThan($today)) {
            return 'That date has already passed.';
        }

        $leadHours = (int) $this->settings->get('availability.min_lead_hours', 24);

        if ($date->endOfDay()->lessThan(now()->addHours($leadHours))) {
            return $leadHours >= 24 ? 'We need at least '.round($leadHours / 24).' day of notice. Please choose a later date.' : "We need at least {$leadHours} hours of notice. Please choose a later date.";
        }

        $maxAdvance = (int) $this->settings->get('availability.max_advance_days', 120);

        if ($date->greaterThan($today->addDays($maxAdvance))) {
            return 'That date is too far ahead. Please choose one within the next '.round($maxAdvance / 30).' months.';
        }

        if (! $this->window($date)) {
            return 'We are closed on '.$date->format('l').'s. Please choose another day.';
        }

        $block = $this->fullDayBlock($date);

        if ($block) {
            return $block->reason ? 'The studio is closed that day: '.$block->reason : 'The studio is closed that day.';
        }

        return null;
    }

    public function isOpenOn(CarbonImmutable $date): bool
    {
        return $this->dateProblem($date) === null;
    }

    /**
     * The earliest and latest dates a visitor may pick at all. The calendar
     * uses these to decide when to stop offering a previous or next month.
     *
     * @return array{min: CarbonImmutable, max: CarbonImmutable}
     */
    public function bookableRange(): array
    {
        $today = CarbonImmutable::today();
        $leadHours = (int) $this->settings->get('availability.min_lead_hours', 24);
        $maxDays = (int) $this->settings->get('availability.max_advance_days', 120);

        $min = $today;

        // Walk forward past any day the lead time already rules out. Bounded by
        // the advance window, so a misconfigured lead time cannot loop away.
        while ($min->endOfDay()->lessThan(now()->addHours($leadHours)) && $min->lessThan($today->addDays($maxDays))) {
            $min = $min->addDay();
        }

        return ['min' => $min, 'max' => $today->addDays($maxDays)];
    }

    /*
    |--------------------------------------------------------------------------
    | Calendar
    |--------------------------------------------------------------------------
    */

    /**
     * One calendar month, every day marked bookable or not with the reason.
     *
     * A day is bookable only if the date-level rules pass AND at least one
     * start time long enough for this session survives the partial blocks —
     * a two-hour closure in the middle of a five-and-a-half hour evening can
     * leave a four-hour session with nowhere to go, and the visitor should see
     * that before they pick the date rather than after.
     *
     * Seats are deliberately NOT consulted here. Day-level seat counting would
     * be a query per slot per day, and the time dropdown already shows seats
     * left for the chosen date. The submit-time check() is the authority
     * either way.
     *
     * @return array<string,mixed>
     */
    public function calendar(Workshop $workshop, CarbonImmutable $month): array
    {
        $month = $month->startOfMonth();
        $range = $this->bookableRange();
        $today = CarbonImmutable::today();

        $days = [];

        for ($day = $month; $day->month === $month->month; $day = $day->addDay()) {
            $problem = $this->dateProblem($day);

            if ($problem === null && ! $this->hasAnySlot($workshop, $day)) {
                $problem = 'No start time that day is long enough for this session.';
            }

            $days[] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'weekday' => $day->dayOfWeek,
                'selectable' => $problem === null,
                'reason' => $problem,
                'is_today' => $day->isSameDay($today),
            ];
        }

        $previous = $month->subMonth();
        $next = $month->addMonth();

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            // Which column the 1st sits in. 0 = Sunday, matching the header the
            // picker renders and the day_of_week convention used everywhere else.
            'first_weekday' => $month->dayOfWeek,
            'days' => $days,
            'prev' => $previous->endOfMonth()->greaterThanOrEqualTo($range['min']) ? $previous->format('Y-m') : null,
            'next' => $next->startOfMonth()->lessThanOrEqualTo($range['max']) ? $next->format('Y-m') : null,
            'min' => $range['min']->toDateString(),
            'max' => $range['max']->toDateString(),
        ];
    }

    /**
     * Clamp a requested month into the bookable window, so the picker cannot be
     * walked a thousand months into the future one request at a time.
     */
    public function clampMonth(CarbonImmutable $month): CarbonImmutable
    {
        $range = $this->bookableRange();

        $first = $range['min']->startOfMonth();
        $last = $range['max']->startOfMonth();

        return $month->lessThan($first) ? $first : ($month->greaterThan($last) ? $last : $month);
    }

    /*
    |--------------------------------------------------------------------------
    | Slots
    |--------------------------------------------------------------------------
    */

    /**
     * Every start time this workshop could begin at on this date, each marked
     * available or not with the reason why.
     *
     * Unavailable slots are returned rather than filtered out on purpose: a
     * visitor who sees "6:00 PM — fully booked" understands the studio is busy,
     * where a silently shortened list looks like a broken form.
     *
     * EACH SLOT CARRIES ITS OWN CEILING. `max` is the largest party that can
     * still join this particular start time — the session's own limit, or the
     * seats actually left, whichever bites first. It is resolved here rather
     * than left to the caller because two callers would otherwise each have
     * their own opinion, and one of them is a browser.
     *
     * $withCapacity is off for the calendar sweep only. Everything a visitor
     * acts on — the time dropdown and check() — leaves it on.
     *
     * @return array<int,array{value:string,label:string,available:bool,seats_left:?int,max:int,note:?string,reason:?string}>
     */
    public function slotsFor(Workshop $workshop, CarbonImmutable $date, bool $withCapacity = true, ?Reservation $except = null): array
    {
        $window = $this->window($date);

        if (! $window) {
            return [];
        }

        $step = (int) $this->settings->get('availability.slot_step_minutes', 30);
        $limits = $this->participantLimits($workshop);
        $counting = $withCapacity && $this->enforcesCapacity();
        $slots = [];

        for ($start = $window['opens']; $start->addMinutes($workshop->duration_minutes)->lessThanOrEqualTo($window['closes']); $start = $start->addMinutes($step)) {
            $end = $start->addMinutes($workshop->duration_minutes);

            $reason = null;
            $seatsLeft = null;
            $max = $limits['max'];

            if ($this->overlapsPartialBlock($date, $start, $end)) {
                $reason = 'Studio unavailable';
            } elseif ($counting) {
                $taken = $this->seatsTaken($workshop, $date, $start, $end, $except);
                $seatsLeft = max(0, $workshop->max_participants - $taken);
                $max = min($limits['max'], $seatsLeft);

                // Unbookable rather than merely tight: nobody can make a party
                // smaller than the session's own minimum.
                if ($seatsLeft < $limits['min']) {
                    $reason = 'Fully booked';
                }
            }

            $slots[] = [
                'value' => $start->format('H:i'),
                'label' => $start->format('g:i A'),
                'available' => $reason === null,
                'seats_left' => $seatsLeft,
                'max' => $max,

                /*
                 | Only when the remaining seats are the binding constraint. A
                 | slot with the full house free saying "12 places left" is
                 | noise on every line of the dropdown; a slot saying "4 places
                 | left" is the one piece of information the visitor needs
                 | before they type 8.
                 */
                'note' => $reason === null && $seatsLeft !== null && $seatsLeft < $limits['max'] ? $seatsLeft.' '.str('place')->plural($seatsLeft).' left' : null,

                'reason' => $reason,
            ];
        }

        return $slots;
    }

    private function hasAnySlot(Workshop $workshop, CarbonImmutable $date): bool
    {
        foreach ($this->slotsFor($workshop, $date, withCapacity: false) as $slot) {
            if ($slot['available']) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Group size
    |--------------------------------------------------------------------------
    */

    /**
     * How many people this session will take, independent of who has already
     * booked it.
     *
     * DECISION: this is enforced whether or not
     * availability.enforce_capacity is on. The two are different rules and were
     * previously conflated. A session's max_participants is a property of the
     * session — how many stools, wheels or screens exist — and it is editable
     * in the workshop admin. enforce_capacity governs something else entirely:
     * whether we subtract seats already committed by other reservations, which
     * is the part that depends on live booking data being trustworthy.
     *
     * CONSEQUENCE, and the client needs to know: the seeded max_participants is
     * still the placeholder 12 from Phase 4, so a request for 15 people is now
     * refused. Entering the real per-session figures in Workshops is what makes
     * this rule correct rather than merely present.
     *
     * @return array{min:int,max:int}
     */
    public function participantLimits(Workshop $workshop): array
    {
        $ceiling = (int) $this->settings->get('reservation.max_participants', 30);

        $min = max(1, (int) $workshop->min_participants);
        $max = (int) $workshop->max_participants ?: $ceiling;

        // The site-wide ceiling caps the session ceiling, never raises it.
        return ['min' => $min, 'max' => max($min, min($max, $ceiling))];
    }

    /*
    |--------------------------------------------------------------------------
    | The decisive check
    |--------------------------------------------------------------------------
    */

    /**
     * The one method that decides whether a submission is acceptable. Called
     * by StoreReservationRequest on every submit, and again by Phase 10 before
     * an admin approves — a slot free at request time may not be free at
     * approval time.
     *
     * @return array{ok: bool, field: ?string, reason: ?string}
     */
    public function check(Workshop $workshop, string $date, string $time, int $participants, ?Reservation $except = null): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        if ($problem = $this->dateProblem($day)) {
            return ['ok' => false, 'field' => 'date', 'reason' => $problem];
        }

        // Group size before slots: it is the cheaper answer and the more useful
        // one. Telling someone the 6:00 PM start is taken, when the real problem
        // is that they entered 40 people, wastes their next attempt.
        $limits = $this->participantLimits($workshop);

        if ($participants < $limits['min']) {
            return [
                'ok' => false,
                'field' => 'participants',
                'reason' => $limits['min'] === 1 ? 'Please enter at least one person.' : "This session runs for {$limits['min']} people or more.",
            ];
        }

        if ($participants > $limits['max']) {
            return [
                'ok' => false,
                'field' => 'participants',
                'reason' => "This session takes up to {$limits['max']} people. ".'For a larger group, please message us and we will arrange it directly.',
            ];
        }

        $slots = $this->slotsFor($workshop, $day, except: $except);
        $match = null;

        foreach ($slots as $slot) {
            if ($slot['value'] === $time) {
                $match = $slot;
                break;
            }
        }

        if (! $match) {
            return [
                'ok' => false,
                'field' => 'time',
                'reason' => 'That start time does not leave enough room for this session. Please pick another.',
            ];
        }

        if (! $match['available']) {
            return [
                'ok' => false,
                'field' => 'time',
                'reason' => $match['reason'] === 'Fully booked' ? 'That time has just filled up. Please choose another.' : 'The studio is not available at that time. Please choose another.',
            ];
        }

        /*
         | Against the slot's own ceiling, which slotsFor() has already worked
         | out. Recomputing the seat count here was a second query AND a second
         | definition of "how many can still come", and the two could disagree
         | the day either one changed.
         */
        if ($this->enforcesCapacity() && $participants > $match['max']) {
            $free = (int) $match['seats_left'];

            return [
                'ok' => false,
                'field' => 'participants',
                'reason' => $free > 0 ? "Only {$free} ".str('place')->plural($free).' left at that time. Please reduce the group or choose another slot.' : 'That time has just filled up. Please choose another.',
            ];
        }

        return ['ok' => true, 'field' => null, 'reason' => null];
    }

    /*
    |--------------------------------------------------------------------------
    | Capacity
    |--------------------------------------------------------------------------
    */

    /**
     * AWAITING CLIENT CONFIRMATION — see the Phase 7A notes.
     *
     * Off by default. This governs seat counting against existing reservations,
     * NOT the group-size ceiling — participantLimits() handles that and applies
     * regardless. Flip availability.enforce_capacity in the availability admin
     * once the real per-session capacities are entered, and the seat pathway
     * activates with no code change.
     */
    public function enforcesCapacity(): bool
    {
        return (bool) $this->settings->get('availability.enforce_capacity', false);
    }

    /**
     * Seats already committed for this workshop in a period that overlaps the
     * proposed one.
     *
     * Overlap, not equality: a 4-hour session starting at 16:00 and a 2-hour
     * one starting at 17:30 are different slots but the same people and the
     * same tables.
     *
     * $except takes a reservation OUT of the count, and exists for one case:
     * re-checking a booking that is already in the register. An Approved or
     * Confirmed reservation holds capacity, so asking "does this fit?" about
     * the very booking occupying the slot would count its four people against
     * its own four seats and refuse an edit that changes nothing. Phase 27 made
     * confirmed bookings editable, which is what turned this from theoretical
     * into reachable.
     *
     * OPEN QUESTION, unchanged: this counts per workshop. If the real
     * constraint is the room rather than the session — two workshops running at
     * once sharing the same tables — this drops the workshop filter and
     * compares against a studio-wide seat count instead. One line here and
     * nowhere else, which is why the filter lives in this method.
     */
    public function seatsTaken(Workshop $workshop, CarbonImmutable $date, CarbonImmutable $start, CarbonImmutable $end, ?Reservation $except = null): int
    {
        return (int) Reservation::query()->onDate($date->toDateString())->holdingCapacity()->when($except?->exists, fn ($q) => $q->whereKeyNot($except->getKey()))->whereHas('items', fn ($q) => $q->where('workshop_id', $workshop->id))->where('start_time', '<', $end->format('H:i:s'))->where('end_time', '>', $start->format('H:i:s'))->sum('participants');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Every block on this date, from the month already in memory.
     *
     * These two lookups used to be a database query each, per slot.
     * The calendar sweep would have run something like 750 of them for one
     * month of a two-hour session. One query per month instead.
     *
     * @return Collection<int,BlockedDate>
     */
    private function blocksOn(CarbonImmutable $date): Collection
    {
        $key = $date->format('Y-m');

        $this->blocks[$key] ??= BlockedDate::query()
            ->whereBetween('date', [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()])
            ->get()
            ->groupBy(fn (BlockedDate $block) => $block->date->toDateString());

        return $this->blocks[$key]->get($date->toDateString()) ?? new Collection;
    }

    private function fullDayBlock(CarbonImmutable $date): ?BlockedDate
    {
        return $this->blocksOn($date)->firstWhere('is_full_day', true);
    }

    private function overlapsPartialBlock(CarbonImmutable $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        $from = $start->format('H:i:s');
        $to = $end->format('H:i:s');

        return $this->blocksOn($date)->contains(function (BlockedDate $block) use ($from, $to) {
            if ($block->is_full_day || ! $block->starts_at || ! $block->ends_at) {
                return false;
            }

            // TIME columns, uncast, so these are 'HH:MM:SS' strings and compare
            // correctly as strings. Same comparison the SQL used to do.
            return $block->starts_at < $to && $block->ends_at > $from;
        });
    }

    /** @return array<int,OperatingHour> keyed by day_of_week */
    private function allHours(): array
    {
        return self::$hours ??= OperatingHour::all()->keyBy('day_of_week')->all();
    }

    private function hoursFor(int $dayOfWeek): ?OperatingHour
    {
        return $this->allHours()[$dayOfWeek] ?? null;
    }

    /** Called by the Phase 7B operating-hours screen after a write. */
    public static function forgetHours(): void
    {
        self::$hours = null;
    }

    /** Call after writing blocked dates within the same request. */
    public function forgetBlocks(): void
    {
        $this->blocks = [];
    }
}
