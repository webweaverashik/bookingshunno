<?php

namespace App\Http\Controllers\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The dashboard.
 *
 * ---------------------------------------------------------------------------
 * GATED BY PERMISSION, NOT BY ROLE
 * ---------------------------------------------------------------------------
 * Every panel is wrapped in a @can in the view, keyed to the permission that
 * governs the screen it links to. That is deliberate and it is not the same as
 * checking for Admin:
 *
 *   A Manager holds payments.view and reports.view. They already see the
 *   payments register and can run a revenue report. Hiding the revenue chart
 *   from them would not protect anything — it would just make the dashboard
 *   disagree with the rest of the panel about what they are allowed to know.
 *
 *   A Manager does NOT hold reservations.approve. So the escalation tile is
 *   hidden from them, because it links to a queue only an Admin can clear, and
 *   a to-do item you cannot act on is noise.
 *
 * The rule: show a panel if the person can act on what it links to. Role checks
 * would have to be revisited every time the seeder moves a permission; these
 * follow it automatically.
 *
 * ---------------------------------------------------------------------------
 * WHY THE COMPUTATION IS UNCONDITIONAL
 * ---------------------------------------------------------------------------
 * Everything is calculated, then the view shows what the person may see. That
 * costs a Manager a few queries whose results are discarded, and it buys the
 * guarantee that a panel can never render against data that was skipped —
 * which is the failure mode of conditional loading, and it presents as an
 * empty chart rather than as an error.
 *
 * At this scale the whole page is a couple of dozen queries against tables
 * holding thousands of rows. If that stops being true, cache summary() and
 * the three trends for five minutes; nothing on this page needs to be
 * accurate to the second.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.dashboard.index', [
            'actions'   => $this->visibleActions($request),
            'summary'   => $this->dashboard->summary(),
            'today'     => $this->dashboard->today(),
            'week'      => $this->dashboard->week(),
            'trend'     => $this->dashboard->reservationTrend(),
            'revenue'   => $this->dashboard->revenueTrend(),
            'workshops' => $this->dashboard->workshopDemand(),
            'balances'  => $this->dashboard->balances(),

            // Admin only, and checked here rather than in the view because the
            // checks themselves query the queue table and the mail config —
            // work with no purpose for somebody who cannot act on the answer.
            'health'    => $request->user()->can('settings.view')
                ? $this->dashboard->health()
                : [],
        ]);
    }

    /**
     * Filter the action strip to tiles this person can actually clear.
     *
     * Done in PHP rather than with @can in the loop so the strip stays an even
     * four-across grid: dropping a tile inside the loop would leave a gap where
     * the escalation tile used to be.
     */
    private function visibleActions(Request $request): array
    {
        return array_values(array_filter(
            $this->dashboard->actions(),
            fn (array $action) => $action['permission'] === null
                || $request->user()->can($action['permission']),
        ));
    }
}
