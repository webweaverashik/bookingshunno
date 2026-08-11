<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Start times a session can actually begin at.
 *
 * The studio runs 16:00–21:30, a 5.5 hour window, but the Immersive session is
 * 4 hours long — so it can only start at 16:00 or 17:30. A flat list of slots
 * would happily sell a 4-hour session at 8pm and then have to decline it.
 * Slots are therefore generated per experience from its duration.
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
    public static function forDuration(int $hours): array
    {
        $opens  = CarbonImmutable::createFromTimeString(config('shunno.operating.session_start'));
        $closes = CarbonImmutable::createFromTimeString(config('shunno.operating.session_end'));

        $slots = [];

        for ($start = $opens; $start->addHours($hours)->lessThanOrEqualTo($closes); $start = $start->addMinutes(self::STEP_MINUTES)) {
            $slots[$start->format('H:i')] = $start->format('g:i A');
        }

        return $slots;
    }

    /**
     * Every slot table the front end needs, keyed by experience slug, so the
     * popup can swap the time list the moment a session is chosen.
     *
     * @return array<string,array<string,string>>
     */
    public static function byExperience(): array
    {
        $map = [];

        foreach (ExperienceCatalogue::all() as $experience) {
            $map[$experience['slug']] = self::forDuration((int) $experience['hours']);
        }

        return $map;
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
