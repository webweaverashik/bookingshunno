<?php

namespace App\Services\Dashboard;

use App\Enums\Communication\CommunicationStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Reservation\ReservationStatus;
use App\Enums\Payment\TransactionStatus;
use App\Enums\Voucher\VoucherType;
use App\Models\Communication\Communication;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use App\Models\Reservation\Reservation;
use App\Models\Voucher\Voucher;
use App\Models\Workshop\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Setting\SettingsRepository;

/**
 * PHASE 23 — everything the dashboard shows.
 *
 * A service rather than a fat controller, for the usual reason and one specific
 * one: several of these figures also appear elsewhere — outstanding money on
 * the payments report, voucher liability on the vouchers report — and they must
 * agree. Keeping them in one class means the dashboard is a VIEW of the system
 * rather than a second opinion about it.
 *
 * ---------------------------------------------------------------------------
 * NO ARITHMETIC THAT ALREADY EXISTS
 * ---------------------------------------------------------------------------
 * Money comes from Payment::outstanding() and Reservation::payableTotal(), the
 * same methods the register and the reports use. Nothing is re-derived here.
 * The one exception is grouping and counting, which is what a dashboard is.
 *
 * ---------------------------------------------------------------------------
 * SERIES ARE BUILT SERVER-SIDE, FORMATTED INCLUDED
 * ---------------------------------------------------------------------------
 * Every chart series carries BOTH raw numbers to plot and pre-formatted strings
 * for the tooltip. That looks redundant and is not: the standing rule is that
 * no money is formatted in the browser, and a chart tooltip rendering
 * "৳12500" via a JavaScript formatter is exactly that rule being broken
 * somewhere nobody looks. The chart plots numbers; the words come from PHP.
 */
class DashboardService
{
    /*
    |--------------------------------------------------------------------------
    | What needs somebody now
    |--------------------------------------------------------------------------
    */

    /**
     * The action strip at the top.
     *
     * Ordered by urgency, not by size. A single overdue payment matters more
     * than forty requests that arrived this morning, and the strip should read
     * as a to-do list rather than as a scoreboard.
     *
     * @return array<int,array{key:string,label:string,count:int,tone:string,hint:string,url:string,permission:?string}>
     */
    public function actions(): array
    {
        $now = CarbonImmutable::now();

        $overdue = Payment::where('status', PaymentStatus::Pending)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->count();

        return [
            [
                'key'        => 'review',
                'label'      => 'Awaiting review',
                'count'      => Reservation::awaitingReview()->count(),
                'tone'       => 'primary',
                'hint'       => 'Requests nobody has answered yet',
                /*
                 | range=all as well as the status.
                 |
                 | The register defaults to "today and ahead". A request that
                 | arrived for a date that has since passed is still unanswered
                 | and still counted here — without range=all the tile would say
                 | 5 and the page it opens would show 3, which is the sort of
                 | thing that makes people stop trusting the number.
                 */
                'url'        => route('admin.reservations.index', [
                    'status' => ReservationStatus::Pending->value,
                    'range'  => 'all',
                ]),
                'permission' => 'reservations.view',
            ],
            [
                /*
                 | Escalations sit above the payment queue on purpose. A Manager
                 | raised one because they could not decide, which means a
                 | booking is stuck behind a person rather than behind a process.
                 */
                'key'        => 'escalated',
                'label'      => 'Escalated to you',
                'count'      => Reservation::where('status', ReservationStatus::Escalated)->count(),
                'tone'       => 'warning',
                'hint'       => 'A manager needs a decision',
                'url'        => route('admin.reservations.index', [
                    'status' => ReservationStatus::Escalated->value,
                    'range'  => 'all',
                ]),
                'permission' => 'reservations.approve',
            ],
            [
                'key'        => 'overdue',
                'label'      => 'Payment overdue',
                'count'      => $overdue,
                'tone'       => 'danger',
                'hint'       => 'Past the deadline, place still held',

                // The register has a dedicated 'overdue' filter that runs the
                // same scope this count came from — so the tile and the page it
                // opens show identical rows, rather than merely similar ones.
                'url'        => route('admin.payments.index', ['status' => 'overdue']),
                'permission' => 'payments.view',
            ],
            [
                'key'        => 'awaiting_payment',
                'label'      => 'Awaiting payment',
                'count'      => Payment::where('status', PaymentStatus::Pending)->count(),
                'tone'       => 'info',
                'hint'       => 'Sent, not yet settled',
                'url'        => route('admin.payments.index', ['status' => 'open']),
                'permission' => 'payments.view',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The floor
    |--------------------------------------------------------------------------
    */

    /**
     * Today's sessions, in the order they happen.
     *
     * The single most operationally useful thing on this page, and the reason
     * it sits above every chart: whoever opens the panel at four in the
     * afternoon wants to know who is coming at five.
     *
     * Everything still alive is included, not only Confirmed — a booking
     * awaiting payment is still somebody who may walk in, and staff need to
     * know which ones those are before the door opens rather than after.
     *
     * @return Collection<int,Reservation>
     */
    public function today(): Collection
    {
        return Reservation::query()
            ->with(['user:id,name,phone', 'items.workshop:id,title', 'payments'])
            ->onDate(CarbonImmutable::today()->toDateString())
            ->whereNotIn('status', [
                ReservationStatus::Declined->value,
                ReservationStatus::Cancelled->value,
            ])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * The next seven days, one row per day.
     *
     * Guests are counted from reservations HOLDING CAPACITY only — the same
     * scope the availability checker uses, so this page and the booking form
     * can never disagree about how full a day is. An unanswered request holds
     * nothing and must not appear to.
     *
     * @return array<int,array{date:CarbonImmutable,sessions:int,guests:int,unsettled:int}>
     */
    public function week(): array
    {
        $start = CarbonImmutable::today();
        $end   = $start->addDays(6);

        $rows = Reservation::query()
            ->whereBetween('reserved_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', [
                ReservationStatus::Declined->value,
                ReservationStatus::Cancelled->value,
            ])
            ->get(['id', 'reserved_date', 'participants', 'status'])
            ->groupBy(fn (Reservation $r) => $r->reserved_date->toDateString());

        $days = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $onDay = $rows->get($day->toDateString(), collect());

            $days[] = [
                'date'     => $day,
                'sessions' => $onDay->count(),

                'guests'   => (int) $onDay
                    ->whereIn('status', [
                        ReservationStatus::Approved,
                        ReservationStatus::PaymentRequested,
                        ReservationStatus::Confirmed,
                    ])
                    ->sum('participants'),

                // Coming, but not paid for. The number staff chase.
                'unsettled' => $onDay
                    ->whereIn('status', [ReservationStatus::Approved, ReservationStatus::PaymentRequested])
                    ->count(),
            ];
        }

        return $days;
    }

    /*
    |--------------------------------------------------------------------------
    | Trends
    |--------------------------------------------------------------------------
    */

    /**
     * Twelve weeks of requests against confirmations.
     *
     * Weekly, not daily: an evening studio has days with nothing at all, and a
     * daily line is mostly zeroes with spikes — which looks like a broken chart
     * rather than like a business. A week is also the unit the studio plans in.
     *
     * Ranged on when the request was MADE, unlike the reservations report which
     * ranges on the visit date. Different question: this one is "is interest
     * growing", and that is about when people ask.
     *
     * @return array{labels:array,requested:array,confirmed:array,rows:array}
     */
    public function reservationTrend(int $weeks = 12): array
    {
        $start = CarbonImmutable::today()->startOfWeek()->subWeeks($weeks - 1);

        $reservations = Reservation::query()
            ->where('created_at', '>=', $start->startOfDay())
            ->get(['id', 'created_at', 'status', 'participants']);

        $labels = $requested = $confirmed = $rows = [];

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $start->addWeeks($i);
            $weekEnd   = $weekStart->addDays(6);

            $inWeek = $reservations->filter(
                fn (Reservation $r) => $r->created_at->betweenIncluded($weekStart->startOfDay(), $weekEnd->endOfDay())
            );

            $settled = $inWeek->whereIn('status', [
                ReservationStatus::Confirmed,
                ReservationStatus::Completed,
            ]);

            $labels[]    = $weekStart->format('j M');
            $requested[] = $inWeek->count();
            $confirmed[] = $settled->count();

            /*
             | The same numbers again, shaped for the table tab.
             |
             | Built here rather than derived in Blade from the three arrays
             | above, because a table that reassembles a chart's arrays by index
             | is one off-by-one away from silently disagreeing with the picture
             | beside it.
             */
            $rows[] = [
                'label'     => $weekStart->format('j M') . ' – ' . $weekEnd->format('j M'),
                'requested' => $inWeek->count(),
                'confirmed' => $settled->count(),
                'guests'    => (int) $settled->sum('participants'),
                'rate'      => $inWeek->count() > 0
                    ? round($settled->count() / $inWeek->count() * 100) . '%'
                    : '—',
            ];
        }

        return compact('labels', 'requested', 'confirmed', 'rows');
    }

    /**
     * Six months of money received.
     *
     * Ranged on when the money ARRIVED, matching the payments report exactly.
     * Voucher settlements are excluded from the cash series and shown as their
     * own — the studio was paid when the gift voucher was sold, and counting the
     * redemption again would overstate income by the value of every coupon
     * honoured.
     *
     * @return array{labels:array,cash:array,voucher:array,tooltips:array,rows:array}
     */
    public function revenueTrend(int $months = 6): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths($months - 1);

        $transactions = PaymentTransaction::query()
            ->receipts()
            ->where('received_at', '>=', $start->startOfDay())
            ->get(['id', 'amount', 'channel', 'received_at']);

        $labels = $cash = $voucher = $tooltips = $rows = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);

            $inMonth = $transactions->filter(
                fn (PaymentTransaction $t) => $t->received_at?->isSameMonth($month)
            );

            $vouchered = $inMonth->filter(fn (PaymentTransaction $t) => $t->channel->value === 'voucher');
            $cashed    = $inMonth->reject(fn (PaymentTransaction $t) => $t->channel->value === 'voucher');

            $cashTotal    = (float) $cashed->sum(fn (PaymentTransaction $t) => (float) $t->amount);
            $voucherTotal = (float) $vouchered->sum(fn (PaymentTransaction $t) => (float) $t->amount);

            $labels[]  = $month->format('M Y');
            $cash[]    = round($cashTotal);
            $voucher[] = round($voucherTotal);

            // Formatted HERE, not in the browser. See the class note.
            $tooltips[] = [
                'cash'    => 'BDT ' . number_format($cashTotal),
                'voucher' => 'BDT ' . number_format($voucherTotal),
            ];

            $rows[] = [
                'label'    => $month->format('F Y'),
                'cash'     => number_format($cashTotal),
                'voucher'  => number_format($voucherTotal),
                'receipts' => $cashed->count(),
                'total'    => number_format($cashTotal + $voucherTotal),
            ];
        }

        return compact('labels', 'cash', 'voucher', 'tooltips', 'rows');
    }

    /**
     * Which experiences people actually book, over the last 90 days.
     *
     * Counted from reservation ITEMS rather than reservations, because one
     * booking can carry more than one workshop and counting the booking would
     * credit only the first.
     *
     * @return array{labels:array,counts:array,rows:array}
     */
    public function workshopDemand(int $days = 90): array
    {
        $since = CarbonImmutable::today()->subDays($days);

        $rows = DB::table('reservation_items')
            ->join('reservations', 'reservations.id', '=', 'reservation_items.reservation_id')
            ->join('workshops', 'workshops.id', '=', 'reservation_items.workshop_id')
            ->whereNull('reservations.deleted_at')
            ->where('reservations.reserved_date', '>=', $since->toDateString())
            ->whereNotIn('reservations.status', [
                ReservationStatus::Declined->value,
                ReservationStatus::Cancelled->value,
            ])
            ->groupBy('workshops.id', 'workshops.title')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get([
                'workshops.id as workshop_id',
                'workshops.title',
                DB::raw('COUNT(*) as bookings'),
                DB::raw('SUM(reservations.participants) as guests'),
            ]);

        return [
            'labels' => $rows->pluck('title')->all(),
            'counts' => $rows->pluck('bookings')->map(fn ($n) => (int) $n)->all(),
            'rows'   => $rows->map(fn ($row) => [
                'id'       => $row->workshop_id,
                'title'    => $row->title,
                'bookings' => (int) $row->bookings,
                'guests'   => (int) $row->guests,
            ])->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Money owed, in both directions
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{owed_to_studio:float,voucher_liability:float,cafe_credit:float,unpaid_count:int}
     */
    public function balances(): array
    {
        $open = Payment::where('status', PaymentStatus::Pending)->get();

        return [
            // What visitors owe. Summed through outstanding() rather than in
            // SQL, so a partially-settled request counts what is left.
            'owed_to_studio' => (float) $open->sum(fn (Payment $p) => $p->outstanding()),
            'unpaid_count'   => $open->count(),

            // What the studio owes, in goods. Every usable code, whenever
            // issued — a liability does not belong to the month it was created.
            'voucher_liability' => (float) Voucher::usable()->sum('value'),
            'cafe_credit'       => (float) Voucher::usable()
                ->where('type', VoucherType::CafeCredit->value)
                ->sum('value'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Is anything broken?
    |--------------------------------------------------------------------------
    */

    /**
     * The system's own vital signs.
     *
     * Every one of these fails SILENTLY. That is the selection criterion — a
     * broken landing page is reported within the hour by whoever sees it; a
     * queue worker that stopped is reported by nobody, and the first symptom is
     * a visitor three days later saying they never got their payment link.
     *
     * @return array<int,array{label:string,state:string,detail:string,url:?string}>
     */
    public function health(): array
    {
        $checks = [];

        /*
         | Mail. The most consequential silent failure in the application: every
         | approval, payment link and receipt goes through it, and when it stops
         | nothing anywhere raises an error.
         */
        $mailer = config('mail.default');
        $failed = Communication::where('status', CommunicationStatus::Failed)
            ->where('created_at', '>=', CarbonImmutable::now()->subDay())
            ->count();

        $checks[] = match (true) {
            $mailer === 'log' => [
                'label'  => 'Outgoing email',
                'state'  => 'bad',
                'detail' => 'Writing to a log file. Nobody is receiving anything.',
                'url'    => route('admin.settings.index'),
            ],
            $failed > 0 => [
                'label'  => 'Outgoing email',
                'state'  => 'warn',
                'detail' => $failed . ' ' . str('message')->plural($failed) . ' failed in the last 24 hours.',
                'url'    => route('admin.reports.show', 'emails'),
            ],
            default => [
                'label'  => 'Outgoing email',
                'state'  => 'ok',
                'detail' => 'Sending through ' . (config('mail.mailers.smtp.host') ?: 'the configured mailer') . '.',
                'url'    => route('admin.settings.index'),
            ],
        };

        /*
         | The gateway, and specifically the mode. Sandbox on a live site is the
         | failure that hides longest: every payment looks successful to
         | everybody and none of it settles, and it surfaces at month end
         | against a bank statement.
         */
        $configured = (bool) config('services.sslcommerz.store_id')
            && (bool) config('services.sslcommerz.store_password');
        $sandbox = (bool) config('services.sslcommerz.sandbox');

        $checks[] = match (true) {
            ! $configured => [
                'label'  => 'Payment gateway',
                'state'  => 'bad',
                'detail' => 'No credentials. Online payment cannot work.',
                'url'    => route('admin.settings.index'),
            ],
            $sandbox && app()->environment('production') => [
                'label'  => 'Payment gateway',
                'state'  => 'bad',
                'detail' => 'SANDBOX mode on a live site. Payments will look successful and never settle.',
                'url'    => route('admin.settings.index'),
            ],
            $sandbox => [
                'label'  => 'Payment gateway',
                'state'  => 'warn',
                'detail' => 'Sandbox mode. Nothing is really charged.',
                'url'    => route('admin.settings.index'),
            ],
            default => [
                'label'  => 'Payment gateway',
                'state'  => 'ok',
                'detail' => 'Live. Transactions move real money.',
                'url'    => route('admin.settings.index'),
            ],
        };

        /*
         | The queue. Email is queued, so a stalled worker means silence rather
         | than errors. Anything sitting for more than a few minutes means the
         | cron entry driving schedule:run is not running.
         */
        $pending = DB::table('jobs')->count();
        $stalled = DB::table('jobs')
            ->where('created_at', '<', CarbonImmutable::now()->subMinutes(10)->getTimestamp())
            ->count();

        $checks[] = match (true) {
            $stalled > 0 => [
                'label'  => 'Queue',
                'state'  => 'bad',
                'detail' => $stalled . ' ' . str('job')->plural($stalled) . ' stuck for over 10 minutes. Check the cron entry.',
                'url'    => null,
            ],
            $pending > 0 => [
                'label'  => 'Queue',
                'state'  => 'ok',
                'detail' => $pending . ' waiting, moving normally.',
                'url'    => null,
            ],
            default => [
                'label'  => 'Queue',
                'state'  => 'ok',
                'detail' => 'Empty.',
                'url'    => null,
            ],
        };

        // Payments SSLCommerz itself flagged. Money arrived and a place is
        // held against a transaction the gateway is unsure about.
        $risky = PaymentTransaction::where('status', TransactionStatus::Success)
            ->where('risk_level', '>=', 1)
            ->where('received_at', '>=', CarbonImmutable::now()->subDays(30))
            ->count();

        if ($risky > 0) {
            $checks[] = [
                'label'  => 'Flagged payments',
                'state'  => 'warn',
                'detail' => $risky . ' ' . str('payment')->plural($risky) . ' marked risky by the gateway in the last 30 days.',
                'url'    => route('admin.reports.show', 'gateway'),
            ];
        }

        // Capacity enforcement, which ships off because the seeded workshop
        // maximums are placeholders. Worth surfacing until the real numbers are
        // in, because until then the studio can be overbooked without warning.
        if (! (bool) app(SettingsRepository::class)->get('availability.enforce_capacity', false)) {
            $checks[] = [
                'label'  => 'Capacity limits',
                'state'  => 'warn',
                'detail' => 'Not enforced. Sessions can be overbooked.',
                'url'    => route('admin.settings.index'),
            ];
        }

        return $checks;
    }

    /*
    |--------------------------------------------------------------------------
    | Headline counters
    |--------------------------------------------------------------------------
    */

    /** @return array<string,int|float> */
    public function summary(): array
    {
        $monthStart = CarbonImmutable::today()->startOfMonth();

        return [
            'received_this_month' => (float) PaymentTransaction::receipts()
                ->where('received_at', '>=', $monthStart)
                ->sum('amount'),

            'visits_this_month' => Reservation::whereBetween('reserved_date', [
                $monthStart->toDateString(),
                CarbonImmutable::today()->endOfMonth()->toDateString(),
            ])->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Completed->value,
            ])->count(),

            'active_workshops' => Workshop::where('is_active', true)->count(),
        ];
    }
}
