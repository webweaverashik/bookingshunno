<?php

namespace App\Support\Availability;

use App\Models\Availability\BlockedDate;
use App\Models\Availability\OperatingHour;
use Carbon\CarbonImmutable;

/**
 * Which days a date picker in the admin should refuse.
 *
 * TWO THINGS ONLY, both of them absolute:
 *
 *   a weekday the studio is closed — Sunday, in the seeded hours
 *   a date blocked for the whole day — a holiday, a private hire
 *
 * Deliberately NOT the whole availability question. Whether a particular
 * session fits into a particular afternoon depends on its duration, on the
 * slot step, on partial closures and on seats already taken, and all of that
 * lives in AvailabilityService and is answered by the time dropdown and by
 * server-side validation. This is the coarse half: the days on which nothing
 * can happen at all, which is what a calendar can usefully grey out before
 * anyone has chosen a session.
 *
 * A partial block is left selectable on purpose. "Closed 2pm to 5pm" does not
 * make the day unbookable, and greying it out would hide a morning the studio
 * could sell.
 *
 * Presentation rather than policy, which is why it sits in Support and reads
 * the two models directly: nothing here decides whether a booking is allowed,
 * only what a picker offers. The server still refuses a bad date whatever the
 * browser let somebody click.
 */
class Closures
{
    /**
     * How far ahead to list blocked dates.
     *
     * Long enough to cover anything the studio has actually scheduled, short
     * enough that the list stays a handful of strings in a data attribute
     * rather than something worth an endpoint.
     */
    private const MONTHS_AHEAD = 18;

    /**
     * @return array{weekdays:array<int,int>,dates:array<int,string>}
     */
    public static function all(): array
    {
        return [
            'weekdays' => self::closedWeekdays(),
            'dates'    => self::blockedDates(),
        ];
    }

    /**
     * Weekday numbers the studio is shut, 0 = Sunday.
     *
     * The column is already stored in JavaScript's own numbering, which is why
     * this needs no translation on the way to Flatpickr — see the note on
     * operating_hours.day_of_week.
     *
     * @return array<int,int>
     */
    public static function closedWeekdays(): array
    {
        return OperatingHour::query()
            ->where('is_closed', true)
            ->orderBy('day_of_week')
            ->pluck('day_of_week')
            ->map(fn ($day) => (int) $day)
            ->all();
    }

    /**
     * Full-day blocks from today onward, as Y-m-d strings.
     *
     * Past blocks are excluded: a picker cannot offer yesterday anyway, and
     * carrying years of history into every page would grow without limit.
     *
     * @return array<int,string>
     */
    public static function blockedDates(): array
    {
        return BlockedDate::query()
            ->where('is_full_day', true)
            ->whereDate('date', '>=', CarbonImmutable::today()->toDateString())
            ->whereDate('date', '<=', CarbonImmutable::today()->addMonths(self::MONTHS_AHEAD)->toDateString())
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->all();
    }

    /**
     * The attributes a Flatpickr field needs to grey these days out.
     *
     * Rendered onto the input in Blade rather than fetched, because the answer
     * is two short lists and an endpoint for them would be a request per modal
     * open for data that changes about once a month.
     *
     * $except keeps one date selectable whatever the lists say. An existing
     * reservation may well sit on a day that has since been closed or blocked,
     * and a picker that refuses the date already in the field would stop
     * somebody correcting the party size without also moving the booking.
     *
     * @return array<string,string>
     */
    public static function pickerAttributes(?string $except = null): array
    {
        return [
            'data-disable-weekdays' => implode(',', self::closedWeekdays()),
            'data-disable-dates'    => implode(',', array_diff(self::blockedDates(), array_filter([$except]))),
            'data-allow-date'       => (string) $except,
        ];
    }
}
