<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AvailabilityRulesRequest;
use App\Http\Requests\Admin\OperatingHoursRequest;
use App\Models\BlockedDate;
use App\Models\OperatingHour;
use App\Services\AvailabilityAdminService;
use App\Services\SettingsRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * One page, three cards: when the studio is open, the rules that govern how far
 * ahead and how late visitors may book, and the dates it is closed.
 *
 * They live together because they answer one question — what can be booked —
 * and splitting them across three screens would make the admin check three
 * places to understand why a date is refused.
 */
class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityAdminService $admin,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function index(): View
    {
        Gate::authorize('viewAny', BlockedDate::class);

        return view('admin.availability.index', [
            'hours'  => $this->weekHours(),
            'blocks' => $this->blocks(),
            'rules'  => [
                'enforce_capacity' => (bool) $this->settings->get('availability.enforce_capacity', false),
                'min_lead_hours'   => (int) $this->settings->get('availability.min_lead_hours', 24),
                'max_advance_days' => (int) $this->settings->get('availability.max_advance_days', 120),
            ],
        ]);
    }

    public function updateHours(OperatingHoursRequest $request): JsonResponse
    {
        Gate::authorize('manageAvailability', BlockedDate::class);

        $days = $request->validated()['days'];

        // Warn, do not refuse: the client may be shortening hours and intending
        // to retire the session that no longer fits. Refusing would leave them
        // unable to do either one first.
        $broken = $this->admin->workshopsBrokenBy($days);

        $this->admin->saveHours($days);

        $message = 'Opening hours saved.';

        if ($broken) {
            $message .= ' Note: ' . implode(', ', $broken) . ' '
                . (count($broken) === 1 ? 'no longer fits' : 'no longer fit')
                . ' in any day and cannot be booked until the hours or the duration change.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'html'    => view('admin.availability.partials.hours', ['hours' => $this->weekHours()])->render(),
                'warning' => $broken,
            ],
        ]);
    }

    public function updateRules(AvailabilityRulesRequest $request): JsonResponse
    {
        Gate::authorize('manageAvailability', BlockedDate::class);

        $rules = $request->validated();

        $this->admin->saveRules($rules);

        return response()->json([
            'success' => true,
            'message' => $rules['enforce_capacity']
                ? 'Booking rules saved. Per-session capacity is now being enforced.'
                : 'Booking rules saved. Per-session capacity is not being enforced.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Seven rows in weekday order, whatever the table happens to contain — a
     * missing row renders as an editable closed day rather than a gap.
     */
    private function weekHours()
    {
        $existing = OperatingHour::all()->keyBy('day_of_week');

        return collect(range(0, 6))->map(fn (int $day) => $existing->get($day) ?? new OperatingHour([
            'day_of_week' => $day,
            'is_closed'   => true,
            'opens_at'    => null,
            'closes_at'   => null,
        ]));
    }

    private function blocks()
    {
        return BlockedDate::query()
            ->with('creator:id,name')
            ->upcoming()
            ->orderBy('date')
            ->orderBy('starts_at')
            ->get();
    }
}
