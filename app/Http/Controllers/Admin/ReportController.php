<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommunicationStatus;
use App\Enums\PaymentChannel;
use App\Enums\ReportType;
use App\Enums\ReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Models\Communication;
use App\Models\PaymentTransaction;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportService;
use App\Support\CsvStream;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PHASE 16 — reports and CSV export.
 *
 * Four reports, one controller, one filter resolver. The last of those is the
 * important one: index(), list() and export() all call filters() and hand the
 * result to the same ReportService method, so a spreadsheet cannot disagree
 * with the screen it was downloaded from.
 *
 * Everything here is read-only. Nothing in this module writes, which is why
 * there are no policies — a Gate on a report that returns rows the register
 * already shows would be ceremony. Authorisation is by permission on the route
 * group, and export carries a second permission of its own because taking the
 * client's entire visitor list off the server is a different act from looking
 * at a page of it.
 */
class ReportController extends Controller
{
    private const PAGE_SIZES = [25, 50, 100];

    /**
     * The longest window anyone may ask for.
     *
     * Three years, and it is a real limit rather than a formality: the visitor
     * report aggregates its range in memory (see ReportService::visitors) and
     * an unbounded range is the one way to make that expensive. It also stops a
     * mistyped year turning a page load into a full table scan.
     */
    private const MAX_RANGE_DAYS = 1096;

    /**
     * How much may be asked for in a format that cannot stream.
     *
     * The CSV writes rows as it reads them and has no ceiling. A spreadsheet is
     * a zip that must be finished before its first byte is valid, and a PDF has
     * to lay out every page — both hold the whole report in memory, on shared
     * hosting where memory_limit is somebody else's decision. Refused with an
     * explanation and a suggestion, rather than dying halfway through with a
     * 500 and half a file.
     */
    private const HEAVY_FORMAT_ROW_LIMIT = 20000;

    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExporter $exporter,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Screen
    |--------------------------------------------------------------------------
    */

    public function index(Request $request, string $report = 'reservations'): View
    {
        $type    = $this->type($report);
        $filters = $this->filters($request, $type);

        return view('admin.reports.index', [
            'report'    => $type,
            'reports'   => ReportType::all(),
            'rows'      => $this->reports->page($type, $filters),
            'summary'   => $this->reports->summary($type, $filters),
            'filters'   => $filters,
            'statuses'  => $this->statusOptions($type),
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    /**
     * The table half, for the AJAX refresh.
     *
     * Returns rendered markup rather than data, per the standing convention:
     * Blade renders, JS swaps. Building report rows in the browser would put
     * money formatting — and therefore a second opinion about the numbers — on
     * the client.
     */
    public function list(Request $request, string $report): JsonResponse
    {
        $type    = $this->type($report);
        $filters = $this->filters($request, $type);

        return response()->json([
            'success' => true,
            'data'    => [
                'html'    => view('admin.reports.partials.' . $type->value, [
                    'report'  => $type,
                    'rows'    => $this->reports->page($type, $filters),
                    'filters' => $filters,
                ])->render(),

                'summary' => view('admin.reports.partials.summary', [
                    'summary' => $this->reports->summary($type, $filters),
                ])->render(),

                // So the Download button carries whatever is currently on screen.
                'export'  => route('admin.reports.export', array_merge(
                    ['report' => $type->value],
                    $this->queryString($filters),
                )),

                /*
                 | The range the SERVER settled on, echoed back.
                 |
                 | This is what the date pickers are set to after every load, so
                 | the toolbar can never disagree with the rows underneath it. It
                 | also covers the cases where the server changed its mind about
                 | what was asked for — a backwards range that was swapped, a
                 | span longer than the cap that was clamped, a malformed date
                 | that fell back to the default. Before this, all three failed
                 | silently and left the picker showing something the table was
                 | not filtered by.
                 */
                'range'   => [
                    'from'       => $filters['from']->format('Y-m-d'),
                    'to'         => $filters['to']->format('Y-m-d'),
                    'from_label' => $filters['from']->format('j M Y'),
                    'to_label'   => $filters['to']->format('j M Y'),
                ],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */

    /**
     * The same rows, streamed as CSV.
     *
     * A GET rather than a POST, and a plain link rather than fetch(), because a
     * browser cannot save a file it received over XHR without staging the whole
     * thing in memory first — which is precisely what the streaming exists to
     * avoid. The link carries the filter state in its query string, refreshed
     * by reports.js whenever a filter changes.
     */
    public function export(Request $request, string $report): StreamedResponse|BinaryFileResponse|JsonResponse
    {
        $type    = $this->type($report);
        $filters = $this->filters($request, $type);
        $format  = in_array($request->query('format'), ['csv', 'xlsx', 'pdf'], true)
            ? $request->query('format')
            : 'csv';

        $stem = sprintf(
            'shunno-%s-%s-to-%s',
            $type->slug(),
            $filters['from']->format('Y-m-d'),
            $filters['to']->format('Y-m-d'),
        );

        if ($format === 'csv') {
            return CsvStream::download(
                "{$stem}.csv",
                $type->csvHeaders(),
                fn (callable $write) => $this->reports->stream($type, $filters, $write),
            );
        }

        /*
         | Counted before building, not while.
         |
         | The alternative is discovering the report is too big by running out
         | of memory partway through writing it, which returns a 500 and a
         | truncated download. One count query is cheap and the message can then
         | say what to do instead.
         */
        $rows = $type === ReportType::Visitors
            ? $this->reports->visitors($filters)->count()
            : $this->reports->query($type, $filters)->count();

        if ($rows > self::HEAVY_FORMAT_ROW_LIMIT) {
            return response()->json([
                'success' => false,
                'message' => 'That is ' . number_format($rows) . ' rows — too many for '
                    . strtoupper($format) . '. Narrow the date range, or export as CSV, which has no limit.',
            ], 422);
        }

        $path = $format === 'xlsx'
            ? $this->exporter->xlsx($type, $filters)
            : $this->exporter->pdf($type, $filters, $this->reports->summary($type, $filters));

        // deleteFileAfterSend, or every export leaves a copy of the studio's
        // visitor list in the system temp directory.
        return response()
            ->download($path, "{$stem}.{$format}")
            ->deleteFileAfterSend(true);
    }

    /*
    |--------------------------------------------------------------------------
    | Clearing a log
    |--------------------------------------------------------------------------
    */

    /**
     * Delete old log entries — and ONLY log entries.
     *
     * Two narrowings, both of which matter more than the feature does:
     *
     *   ONLY THE TWO LOGS. isClearable() refuses the rest. The reservations,
     *   payments, visitors and vouchers reports are views over the business
     *   itself; a clear button on those is a delete button on the studio's
     *   records.
     *
     *   THE GATEWAY LOG NEVER DELETES A SUCCESSFUL TRANSACTION. payment_
     *   transactions holds receipts and failed attempts in the same table —
     *   Phase 13 put them there deliberately. A clear that took the whole table
     *   would destroy the payment audit trail, every payslip a visitor might
     *   ask to see again, and the figures the payments report is built from.
     *   Only attempts that did not complete are removed.
     *
     * A cutoff in days rather than "delete everything", because the useful
     * version of this is housekeeping — clearing noise older than a quarter —
     * and the destructive version is rarely what anybody means.
     */
    public function clear(Request $request, string $report): JsonResponse
    {
        $type = $this->type($report);

        abort_unless($type->isClearable(), 404);

        $validated = $request->validate([
            'older_than_days' => ['required', 'integer', 'in:0,30,90,365'],
        ]);

        $days   = (int) $validated['older_than_days'];
        $cutoff = $days > 0 ? now()->subDays($days) : null;

        $deleted = match ($type) {
            ReportType::Emails  => $this->clearEmails($cutoff),
            ReportType::Gateway => $this->clearGateway($cutoff),
            default             => 0,
        };

        return response()->json([
            'success' => true,
            'message' => $deleted === 0
                ? 'Nothing to clear.'
                : number_format($deleted) . ' ' . str('entry')->plural($deleted) . ' removed.',
        ]);
    }

    private function clearEmails(?Carbon $cutoff): int
    {
        $query = Communication::query();

        if ($cutoff) {
            $query->where('queued_at', '<', $cutoff);
        }

        return $query->delete();
    }

    private function clearGateway(?Carbon $cutoff): int
    {
        $query = PaymentTransaction::query()
            ->where('channel', PaymentChannel::Gateway->value)

            // The line that must never be removed. A successful transaction is
            // a receipt, not a log entry.
            ->where('status', '!=', TransactionStatus::Success->value);

        if ($cutoff) {
            $query->where('created_at', '<', $cutoff);
        }

        return $query->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function type(string $report): ReportType
    {
        return ReportType::tryFrom($report) ?? abort(404);
    }

    /**
     * Resolve the request into a range and a filter, safely.
     *
     * Every value is validated here and nowhere else. A date arriving as
     * "2026-13-45" or "'; DROP" must not reach a query, and the status value
     * becomes part of a WHERE — so both are checked against a known set rather
     * than trusted.
     *
     * @return array{from:CarbonImmutable,to:CarbonImmutable,status:string,per_page:int}
     */
    private function filters(Request $request, ReportType $report): array
    {
        $today = CarbonImmutable::today();

        /*
         | A named range wins over explicit dates.
         |
         | THE QUICK-RANGE BUTTONS RESOLVE HERE, NOT IN THE BROWSER. They used
         | to build two date strings in JavaScript and post them, which meant
         | the meaning of "this month" depended on the clock and the timezone of
         | whatever machine had the page open — and left the browser holding a
         | date the server then had to re-parse. One authority now: the button
         | posts a KEY, the server decides what it means, and the resolved dates
         | come back in the response for the pickers to display.
         */
        $named = $this->namedRange((string) $request->query('range'), $today);

        if ($named) {
            [$from, $to] = $named;
        } else {
            // Default window: this calendar month. It is what somebody opening a
            // report at the end of a month is almost always after, and it makes
            // the first page load bounded rather than "everything ever".
            $from = $this->date($request->query('from'), $today->startOfMonth());
            $to   = $this->date($request->query('to'), $today->endOfMonth());
        }

        // Typed backwards. Swapped rather than rejected — the intent is obvious
        // and an error message here helps nobody.
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_RANGE_DAYS);
        }

        $status  = (string) $request->query('status', 'all');
        $perPage = (int) $request->query('per_page', 25);

        return [
            'from'     => $from,
            'to'       => $to,
            'status'   => array_key_exists($status, $this->statusOptions($report)) ? $status : 'all',
            'per_page' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25,
        ];
    }

    /**
     * The four windows the toolbar buttons offer.
     *
     * Kept server-side so "this month" means the same thing for everybody,
     * whatever their machine thinks the date is. Returns null for anything
     * unrecognised, which falls through to the explicit from/to.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}|null
     */
    private function namedRange(string $key, CarbonImmutable $today): ?array
    {
        return match ($key) {
            'this-month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last-month' => [
                $today->subMonthNoOverflow()->startOfMonth(),
                $today->subMonthNoOverflow()->endOfMonth(),
            ],
            'quarter'    => [$today->subDays(89), $today],
            'year'       => [$today->startOfYear(), $today->endOfYear()],
            default      => null,
        };
    }

    private function date(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return $fallback;
        }

        // createFromFormat with an exact mask, so only a real yyyy-mm-dd gets
        // through. parse() would cheerfully accept "next tuesday" and a good
        // deal else besides.
        $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value);

        return $parsed && $parsed->format('Y-m-d') === $value ? $parsed->startOfDay() : $fallback;
    }

    /**
     * The second dropdown, which means something different in each report.
     *
     * One key ('status') carries it in all four, so the URL, the filter
     * resolver and the export link stay uniform — the alternative is four
     * query parameters, three of which are always absent.
     *
     * @return array<string,string>
     */
    private function statusOptions(ReportType $report): array
    {
        return match ($report) {
            ReportType::Reservations => [
                'all'     => 'Every status',
                'settled' => 'Confirmed and completed',
                'open'    => 'Still open',
                'lost'    => 'Declined, cancelled or missed',
            ] + collect(ReservationStatus::cases())
                ->mapWithKeys(fn (ReservationStatus $s) => [$s->value => $s->label()])
                ->all(),

            ReportType::Visitors => ['all' => 'Everyone who visited'],

            ReportType::Payments => ['all' => 'Every channel']
                + collect(PaymentChannel::cases())
                    ->mapWithKeys(fn (PaymentChannel $c) => [$c->value => $c->label()])
                    ->all(),

            ReportType::Emails => ['all' => 'Every message']
                + collect(CommunicationStatus::cases())
                    ->mapWithKeys(fn (CommunicationStatus $c) => [$c->value => $c->label()])
                    ->all(),

            ReportType::Gateway => ['all' => 'Every attempt']
                + collect(TransactionStatus::cases())
                    ->mapWithKeys(fn (TransactionStatus $t) => [$t->value => $t->label()])
                    ->all(),

            /*
             | PHASE 21 — filtered by key PREFIX, matching the tabs on the
             | settings screen. The values here are what changesQuery() runs a
             | LIKE against, so 'sslcommerz.' catches every gateway key without
             | listing them.
             */
            ReportType::Changes => [
                'all'          => 'Every change',
                'sslcommerz.'  => 'Payment gateway',
                'mail.'        => 'Email',
                'availability.' => 'Reservations',
                'contact.'     => 'Studio contact',
                'group_discount.' => 'Group discount',
                'cafe_credit.' => 'Café credit',
            ],

            ReportType::Vouchers => [
                'all'         => 'Everything issued',
                'gift'        => 'Gift vouchers',
                'cafe_credit' => 'Café credit',
                'outstanding' => 'Still spendable',
            ] + collect(VoucherStatus::cases())
                ->mapWithKeys(fn (VoucherStatus $s) => [$s->value => $s->label()])
                ->all(),
        };
    }

    /** @return array<string,string> */
    private function queryString(array $filters): array
    {
        return [
            'from'     => $filters['from']->format('Y-m-d'),
            'to'       => $filters['to']->format('Y-m-d'),
            'status'   => $filters['status'],
            'per_page' => (string) $filters['per_page'],
        ];
    }
}
