<?php

namespace App\Services;

use App\Models\BlockedDate;
use App\Models\OperatingHour;
use App\Models\Reservation;
use App\Models\Workshop;
use Carbon\CarbonImmutable;

/**
 * The single authority on whether a given session can be booked at a given time.
 *
 * Replaces App\Support\SessionSlots entirely. SessionSlots read the operating
 * window from config, so the hours the client can already edit in the database
 * had no effect on anything. It also knew nothing about blocked dates or seats
 * already taken.
 *
 * Every answer here is derived server-side. The reservation popup calls
 * slotsFor() through /availability purely so the visitor sees a sensible list;
 * StoreReservationRequest calls check() again on submit and that second call is
 * the one that decides. Nothing the browser sends is trusted (§19).
 *
 * Ordering of the rules matters: the cheapest and most decisive checks run
 * first, so a request for a Sunday never reaches a capacity query.
 */
class AvailabilityService
{
    /** @var array<int,OperatingHour>|null */
    private static ?array $hours = null;

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

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
            'opens'  => $date->setTimeFromTimeString($hours->opens_at),
            'closes' => $date->setTimeFromTimeString($hours->closes_at),
        ];
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

            $opens  = CarbonImmutable::createFromTimeString($hours->opens_at);
            $closes = CarbonImmutable::createFromTimeString($hours->closes_at);

            $longest = max($longest, (int) $opens->diffInMinutes($closes));
        }

        // Falls back to the shipped 16:00–21:30 window if the table is empty,
        // so a half-seeded database cannot make every duration invalid.
        return $longest ?: 330;
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
            return $leadHours >= 24
                ? 'We need at least ' . round($leadHours / 24) . ' day of notice. Please choose a later date.'
                : "We need at least {$leadHours} hours of notice. Please choose a later date.";
        }

        $maxAdvance = (int) $this->settings->get('availability.max_advance_days', 120);

        if ($date->greaterThan($today->addDays($maxAdvance))) {
            return 'That date is too far ahead. Please choose one within the next '
                . round($maxAdvance / 30) . ' months.';
        }

        if (! $this->window($date)) {
            return 'We are closed on ' . $date->format('l') . 's. Please choose another day.';
        }

        $block = $this->fullDayBlock($date);

        if ($block) {
            return $block->reason
                ? 'The studio is closed that day: ' . $block->reason
                : 'The studio is closed that day.';
        }

        return null;
    }

    public function isOpenOn(CarbonImmutable $date): bool
    {
        return $this->dateProblem($date) === null;
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
     * @return array<int,array{value:string,label:string,available:bool,seats_left:?int,reason:?string}>
     */
    public function slotsFor(Workshop $workshop, CarbonImmutable $date): array
    {
        $window = $this->window($date);

        if (! $window) {
            return [];
        }

        $step  = (int) $this->settings->get('availability.slot_step_minutes', 30);
        $slots = [];

        for (
            $start = $window['opens'];
            $start->addMinutes($workshop->duration_minutes)->lessThanOrEqualTo($window['closes']);
            $start = $start->addMinutes($step)
        ) {
            $end = $start->addMinutes($workshop->duration_minutes);

            $reason    = null;
            $seatsLeft = null;

            if ($this->overlapsPartialBlock($date, $start, $end)) {
                $reason = 'Studio unavailable';
            } elseif ($this->enforcesCapacity()) {
                $taken     = $this->seatsTaken($workshop, $date, $start, $end);
                $seatsLeft = max(0, $workshop->max_participants - $taken);

                if ($seatsLeft < $workshop->min_participants) {
                    $reason = 'Fully booked';
                }
            }

            $slots[] = [
                'value'      => $start->format('H:i'),
                'label'      => $start->format('g:i A'),
                'available'  => $reason === null,
                'seats_left' => $seatsLeft,
                'reason'     => $reason,
            ];
        }

        return $slots;
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
    public function check(Workshop $workshop, string $date, string $time, int $participants): array
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        if ($problem = $this->dateProblem($day)) {
            return ['ok' => false, 'field' => 'date', 'reason' => $problem];
        }

        $slots = $this->slotsFor($workshop, $day);
        $match = null;

        foreach ($slots as $slot) {
            if ($slot['value'] === $time) {
                $match = $slot;
                break;
            }
        }

        if (! $match) {
            return [
                'ok'     => false,
                'field'  => 'time',
                'reason' => 'That start time does not leave enough room for this session. Please pick another.',
            ];
        }

        if (! $match['available']) {
            return [
                'ok'     => false,
                'field'  => 'time',
                'reason' => $match['reason'] === 'Fully booked'
                    ? 'That time has just filled up. Please choose another.'
                    : 'The studio is not available at that time. Please choose another.',
            ];
        }

        if ($this->enforcesCapacity()) {
            $start = $day->setTimeFromTimeString($time);
            $end   = $start->addMinutes($workshop->duration_minutes);
            $free  = $workshop->max_participants - $this->seatsTaken($workshop, $day, $start, $end);

            if ($participants > $free) {
                return [
                    'ok'     => false,
                    'field'  => 'participants',
                    'reason' => $free > 0
                        ? "Only {$free} " . str('place')->plural($free) . ' left at that time. Please reduce the group or choose another slot.'
                        : 'That time has just filled up. Please choose another.',
                ];
            }
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
     * Off by default. The seeded max_participants is the placeholder 12 from
     * Phase 4; turning enforcement on against an unconfirmed number would start
     * refusing genuine bookings. Flip availability.enforce_capacity to true in
     * settings once the real per-session capacities are entered in the
     * workshop admin, and every rule above starts applying with no code change.
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
     * OPEN QUESTION: this counts per workshop. If the real constraint is the
     * room rather than the session — two different workshops running at once
     * sharing the same tables — this needs to drop the workshop filter and
     * compare against a studio-wide seat count instead. That is a one-line
     * change here and nowhere else, which is why the filter lives in this
     * method rather than in the callers.
     */
    public function seatsTaken(
        Workshop $workshop,
        CarbonImmutable $date,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        return (int) Reservation::query()
            ->onDate($date->toDateString())
            ->holdingCapacity()
            ->whereHas('items', fn ($q) => $q->where('workshop_id', $workshop->id))
            ->where('start_time', '<', $end->format('H:i:s'))
            ->where('end_time', '>', $start->format('H:i:s'))
            ->sum('participants');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function fullDayBlock(CarbonImmutable $date): ?BlockedDate
    {
        return BlockedDate::query()
            ->whereDate('date', $date->toDateString())
            ->where('is_full_day', true)
            ->first();
    }

    private function overlapsPartialBlock(
        CarbonImmutable $date,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): bool {
        return BlockedDate::query()
            ->whereDate('date', $date->toDateString())
            ->where('is_full_day', false)
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $end->format('H:i:s'))
            ->where('ends_at', '>', $start->format('H:i:s'))
            ->exists();
    }

    /** @return array<int,OperatingHour> keyed by day_of_week */
    private function allHours(): array
    {
        return self::$hours ??= OperatingHour::all()
            ->keyBy('day_of_week')
            ->all();
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
}
