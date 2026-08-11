<?php

namespace App\Support;

use App\Models\OperatingHour;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Start times a session can actually begin at.
 *
 * The studio runs 16:00–21:30 — a 5.5 hour window — but the Immersive session
 * is 4 hours long, so it can only start at 16:00 or 17:30. A flat list of slots
 * would happily offer an 8pm start and then have to decline it. Slots are
 * therefore derived per workshop from its duration.
 *
 * Phase 4 change: opening hours now come from the operating_hours table rather
 * than config, so the client can change them without a deploy.
 *
 * PHASE 7: this grows into AvailabilityService, which additionally subtracts
 * blocked_dates and seats already committed on the chosen day. The generation
 * rule below stays exactly as it is.
 */
class SessionSlots
{
    private const STEP_MINUTES = 30;
    private const CACHE_KEY    = 'shunno.slots';

    /**
     * @return array<string,string>  ['16:00' => '4:00 PM', ...]
     */
    public static function forDuration(int $minutes, int $dayOfWeek = 1): array
    {
        $hours = self::hoursFor($dayOfWeek);

        if ($hours === null) {
            return [];
        }

        [$opensAt, $closesAt] = $hours;

        $opens  = CarbonImmutable::createFromTimeString($opensAt);
        $closes = CarbonImmutable::createFromTimeString($closesAt);

        $slots = [];

        for ($start = $opens; $start->addMinutes($minutes)->lessThanOrEqualTo($closes); $start = $start->addMinutes(self::STEP_MINUTES)) {
            $slots[$start->format('H:i')] = $start->format('g:i A');
        }

        return $slots;
    }

    /**
     * Every slot table the popup needs, keyed by workshop slug, so the time
     * list can change the moment a session is chosen.
     *
     * @return array<string,array<string,string>>
     */
    public static function byWorkshop(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return Workshop::active()
                ->ordered()
                ->get()
                ->mapWithKeys(fn (Workshop $w) => [
                    $w->slug => self::forDuration($w->duration_minutes),
                ])
                ->all();
        });
    }

    public static function isOpenOn(string $date): bool
    {
        $day = CarbonImmutable::parse($date)->dayOfWeek;

        return self::hoursFor($day) !== null;
    }

    /** Days the studio is shut, for the popup's date field. */
    public static function closedDays(): array
    {
        return OperatingHour::where('is_closed', true)
            ->pluck('day_of_week')
            ->map(fn ($d) => (int) $d)
            ->values()
            ->all();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{0:string,1:string}|null  null when closed
     */
    private static function hoursFor(int $dayOfWeek): ?array
    {
        $row = OperatingHour::firstWhere('day_of_week', $dayOfWeek);

        // No row at all means the table has not been seeded; fall back to the
        // shipped defaults rather than silently offering nothing.
        if ($row === null) {
            return in_array($dayOfWeek, config('shunno.operating.closed_days'), true)
                ? null
                : [config('shunno.operating.session_start'), config('shunno.operating.session_end')];
        }

        if ($row->is_closed || ! $row->opens_at || ! $row->closes_at) {
            return null;
        }

        return [(string) $row->opens_at, (string) $row->closes_at];
    }
}
