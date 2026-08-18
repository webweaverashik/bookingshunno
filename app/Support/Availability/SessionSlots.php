<?php

namespace App\Support\Availability;

use App\Models\Workshop\Workshop;
use Carbon\CarbonImmutable;

/**
 * Start times a session can actually begin at.
 *
 * The studio runs 16:00–21:30, a 5.5 hour window, but the Immersive session is
 * 4 hours long — so it can only start at 16:00 or 17:30. A flat list of slots
 * would happily sell a 4-hour session at 8pm and then have to decline it.
 * Slots are therefore generated per workshop from its duration.
 *
 * PHASE 6: durations are now editable in the admin panel and are stored in
 * minutes, so this class works in minutes too. forDuration(int $hours) is gone;
 * a 90-minute session used to floor to 1 hour and offer a slot that did not fit.
 *
 * PHASE 7: this becomes AvailabilityService, which will additionally subtract
 * blocked dates and seats already taken. The generation rule stays the same.
 */
class SessionSlots
{
    private const STEP_MINUTES = 30;

    /**
     * @return array<string,string>  ['16:00' => '4:00 PM', ...]
     */
    public static function forMinutes(int $minutes): array
    {
        $opens  = CarbonImmutable::createFromTimeString(config('shunno.operating.session_start'));
        $closes = CarbonImmutable::createFromTimeString(config('shunno.operating.session_end'));

        $slots = [];

        for (
            $start = $opens;
            $start->addMinutes($minutes)->lessThanOrEqualTo($closes);
            $start = $start->addMinutes(self::STEP_MINUTES)
        ) {
            $slots[$start->format('H:i')] = $start->format('g:i A');
        }

        return $slots;
    }

    /**
     * Minutes between opening and closing. A workshop longer than this can
     * never be scheduled, which is why the admin form rejects it outright.
     */
    public static function windowMinutes(): int
    {
        $opens  = CarbonImmutable::createFromTimeString(config('shunno.operating.session_start'));
        $closes = CarbonImmutable::createFromTimeString(config('shunno.operating.session_end'));

        return (int) $opens->diffInMinutes($closes);
    }

    /**
     * Every slot table the front end needs, keyed by workshop slug, so the
     * popup can swap the time list the moment a session is chosen.
     *
     * @return array<string,array<string,string>>
     */
    public static function byExperience(): array
    {
        return Workshop::menu()
            ->mapWithKeys(fn (Workshop $workshop) => [
                $workshop->slug => self::forMinutes($workshop->duration_minutes),
            ])
            ->all();
    }

    public static function isOpenOn(string $date): bool
    {
        return ! in_array(
            CarbonImmutable::parse($date)->dayOfWeek,
            config('shunno.operating.closed_days'),
            true
        );
    }
}
