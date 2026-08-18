<?php

namespace App\Services\Availability;

use App\Models\Availability\BlockedDate;
use App\Models\Availability\OperatingHour;
use App\Models\Reservation\Reservation;
use App\Models\Workshop\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use App\Services\Setting\SettingsRepository;

/**
 * Every write to the availability configuration goes through here.
 *
 * Two things must happen on any change and neither is obvious from the call
 * site, which is why they are not left to controllers: the operating-hours memo
 * inside AvailabilityService has to be dropped, and the settings cache has to
 * be flushed. Miss either and the admin saves a change that appears to do
 * nothing until the next request.
 */
class AvailabilityAdminService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly AvailabilityService $availability,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Operating hours
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<int,array{day_of_week:int,is_closed:bool,opens_at:?string,closes_at:?string}>  $days
     */
    public function saveHours(array $days): void
    {
        DB::transaction(function () use ($days) {
            foreach ($days as $day) {
                OperatingHour::updateOrCreate(
                    ['day_of_week' => (int) $day['day_of_week']],
                    [
                        'is_closed' => (bool) $day['is_closed'],
                        'opens_at'  => $day['is_closed'] ? null : $day['opens_at'],
                        'closes_at' => $day['is_closed'] ? null : $day['closes_at'],
                    ],
                );
            }
        });

        AvailabilityService::forgetHours();
    }

    /**
     * Active workshops that would no longer fit in the longest remaining
     * window. Shown as a warning rather than a hard block: the client may
     * legitimately be shortening hours and intending to retire a session, and
     * refusing the save would leave them unable to do either first.
     *
     * @param  array<int,array<string,mixed>>  $days
     * @return array<int,string>  workshop titles
     */
    public function workshopsBrokenBy(array $days): array
    {
        $longest = 0;

        foreach ($days as $day) {
            if (! empty($day['is_closed']) || empty($day['opens_at']) || empty($day['closes_at'])) {
                continue;
            }

            $opens  = CarbonImmutable::createFromTimeString($day['opens_at']);
            $closes = CarbonImmutable::createFromTimeString($day['closes_at']);

            $longest = max($longest, (int) $opens->diffInMinutes($closes));
        }

        return Workshop::query()
            ->active()
            ->where('duration_minutes', '>', $longest)
            ->orderBy('duration_minutes')
            ->pluck('title')
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Booking rules
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string,mixed>  $rules
     */
    public function saveRules(array $rules): void
    {
        $this->settings->set('availability.enforce_capacity', $rules['enforce_capacity'] ? '1' : '0', 'boolean');
        $this->settings->set('availability.min_lead_hours', (string) $rules['min_lead_hours'], 'integer');
        $this->settings->set('availability.max_advance_days', (string) $rules['max_advance_days'], 'integer');

        // set() flushes on every call; doing it once more costs nothing and
        // makes the intent explicit if a key is added later.
        $this->settings->flush();
    }

    /*
    |--------------------------------------------------------------------------
    | Blocked dates
    |--------------------------------------------------------------------------
    */

    public function block(array $data): BlockedDate
    {
        return BlockedDate::create($this->attributes($data));
    }

    public function updateBlock(BlockedDate $block, array $data): BlockedDate
    {
        $block->update($this->attributes($data));

        return $block->refresh();
    }

    public function unblock(BlockedDate $block): void
    {
        $block->delete();
    }

    /**
     * Reservations already holding capacity inside the period about to be
     * blocked.
     *
     * Blocking a date does not cancel anything — that is a decision for a
     * human, and silently stranding a confirmed visitor would be worse than
     * refusing the block. The count is surfaced so the admin confirms
     * deliberately and knows to contact those people.
     *
     * @return int
     */
    public function reservationsAffectedBy(array $data, ?BlockedDate $ignore = null): int
    {
        $query = Reservation::query()
            ->onDate($data['date'])
            ->holdingCapacity();

        if (empty($data['is_full_day'])) {
            $query->where('start_time', '<', $data['ends_at'])
                ->where('end_time', '>', $data['starts_at']);
        }

        return (int) $query->count();
    }

    /**
     * @return array<string,mixed>
     */
    private function attributes(array $data): array
    {
        $fullDay = (bool) ($data['is_full_day'] ?? true);

        return [
            'date'        => $data['date'],
            'is_full_day' => $fullDay,
            'starts_at'   => $fullDay ? null : $data['starts_at'],
            'ends_at'     => $fullDay ? null : $data['ends_at'],
            'reason'      => $data['reason'] ?? null,
        ];
    }
}
