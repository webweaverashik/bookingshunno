<?php

namespace App\Services\Reports;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ReportType;
use App\Enums\ReservationStatus;
use App\Enums\VoucherType;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Reservation;
use App\Models\Voucher;
use App\Support\CsvStream;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * PHASE 16 — the four reports.
 *
 * One class rather than four, because the reports share the thing that is worth
 * sharing — a date range, a status filter, and the rule that the export must
 * show exactly what the screen shows — and differ only in their query. Four
 * classes would duplicate the filter handling four times, which is where a
 * report and its own CSV drift apart.
 *
 * THE RULE THAT MATTERS: the screen and the export run the SAME query, built by
 * the same method, from the same resolved filters. The only difference is that
 * one paginates and the other chunks. If those ever diverge, somebody exports a
 * spreadsheet that disagrees with what they were looking at, and they will
 * believe the spreadsheet.
 *
 * No arithmetic that already exists elsewhere is repeated here. Reservation
 * totals come from payableTotal() and amountPaid(), voucher usability from
 * isRedeemable(), and so on — the report is a view of the system, not a second
 * opinion about it.
 */
class ReportService
{
    /** Rows read at a time when streaming an export. */
    private const CHUNK = 500;

    /*
    |--------------------------------------------------------------------------
    | The query
    |--------------------------------------------------------------------------
    */

    /**
     * The one query behind both the table and the CSV.
     *
     * @param  array{from:CarbonImmutable,to:CarbonImmutable,status:string}  $filters
     */
    public function query(ReportType $report, array $filters): Builder
    {
        return match ($report) {
            ReportType::Reservations => $this->reservationsQuery($filters),
            ReportType::Visitors     => $this->reservationsQuery($filters),  // aggregated later
            ReportType::Payments     => $this->paymentsQuery($filters),
            ReportType::Vouchers     => $this->vouchersQuery($filters),
        };
    }

    /**
     * What the screen shows.
     *
     * The visitor report is the exception and returns a plain Collection: it is
     * an aggregate over the range, so there is no row in any table to paginate.
     * See visitors() for the size bound that makes that safe.
     */
    public function page(ReportType $report, array $filters): LengthAwarePaginator|Collection
    {
        if ($report === ReportType::Visitors) {
            return $this->visitors($filters);
        }

        return $this->query($report, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /*
    |--------------------------------------------------------------------------
    | Reservations
    |--------------------------------------------------------------------------
    */

    private function reservationsQuery(array $filters): Builder
    {
        $query = Reservation::query()
            ->with(['user:id,name,email,phone', 'items.workshop:id,title', 'purposes:id,name', 'payments'])
            ->whereBetween('reserved_date', [$filters['from']->toDateString(), $filters['to']->toDateString()]);

        /*
         | 'settled' groups Confirmed with Completed on purpose. To a studio
         | counting a month, a visit that happened and a visit that is paid for
         | and about to happen are the same kind of thing; the distinction is
         | operational, and the register already exposes it.
         */
        match ($filters['status']) {
            'all'      => null,
            'settled'  => $query->whereIn('status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::Completed->value,
            ]),
            'open'     => $query->open(),
            'lost'     => $query->whereIn('status', [
                ReservationStatus::Declined->value,
                ReservationStatus::Cancelled->value,
                ReservationStatus::NoShow->value,
            ]),
            default    => $query->where('status', $filters['status']),
        };

        return $query->orderBy('reserved_date')->orderBy('start_time')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Visitors
    |--------------------------------------------------------------------------
    */

    /**
     * Who came in this window, how often, and what they spent.
     *
     * THE ONE REPORT THAT MATERIALISES ITS RANGE. Every other report streams;
     * this one cannot, because it is an aggregate and there is no table of
     * per-visitor-per-range rows to stream from. Doing it in SQL would need a
     * correlated sum over payments inside a group-by, which is both slower and
     * far harder to keep in step with Reservation::amountPaid().
     *
     * The bound that makes it safe is the range cap in
     * ReportController::filters() — no window longer than three years — and the
     * scale of the business: this is an artist-run studio running evening
     * sessions, so a year is hundreds of reservations, not millions. If that
     * ever stops being true, this is the method to rewrite, and it is the only
     * one.
     *
     * @return Collection<int,object>
     */
    public function visitors(array $filters): Collection
    {
        return $this->reservationsQuery(['status' => 'all'] + $filters)
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $reservations) {
                $user = $reservations->first()->user;

                return (object) [
                    'user'         => $user,
                    'visits'       => $reservations->count(),
                    'participants' => (int) $reservations->sum('participants'),

                    // Billed, not "expected": a declined request was never
                    // money, so only reservations the studio stood behind count.
                    'billed'       => (float) $reservations
                        ->reject(fn (Reservation $r) => in_array($r->status, [
                            ReservationStatus::Declined,
                            ReservationStatus::Cancelled,
                        ], true))
                        ->sum(fn (Reservation $r) => $r->payableTotal()),

                    'paid'         => (float) $reservations->sum(fn (Reservation $r) => $r->amountPaid()),
                    'last'         => $reservations->max('reserved_date'),
                ];
            })
            ->sortByDesc('paid')
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    /**
     * Money that actually moved, ranged on when it arrived.
     *
     * Receipts only. Failed gateway attempts live in the same table — Phase 13
     * put them there deliberately, so a visitor who tried four times leaves a
     * trail — but they are not income and must never reach a payment report.
     */
    private function paymentsQuery(array $filters): Builder
    {
        $query = PaymentTransaction::query()
            ->receipts()
            ->with([
                'payment:id,reference,reservation_id,type,percentage',
                'payment.reservation:id,reference_code,user_id,reserved_date',
                'payment.reservation.user:id,name,email',
                'recordedBy:id,name',
            ])
            ->whereBetween('received_at', [
                $filters['from']->startOfDay(),
                $filters['to']->endOfDay(),
            ]);

        if ($filters['status'] !== 'all') {
            // Channel, not status — every row here is already a success. The
            // filter key is shared across reports so the URL stays uniform.
            $query->where('channel', $filters['status']);
        }

        return $query->orderBy('received_at')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Vouchers
    |--------------------------------------------------------------------------
    */

    private function vouchersQuery(array $filters): Builder
    {
        $query = Voucher::query()
            ->with([
                'reservation:id,reference_code,reserved_date',
                'workshop:id,title',
                'redeemedForReservation:id,reference_code',
                'redeemedBy:id,name',
                'issuedBy:id,name',
            ])
            ->whereBetween('created_at', [
                $filters['from']->startOfDay(),
                $filters['to']->endOfDay(),
            ]);

        match ($filters['status']) {
            'all'         => null,
            'gift'        => $query->where('type', VoucherType::Gift->value),
            'cafe_credit' => $query->where('type', VoucherType::CafeCredit->value),
            'outstanding' => $query->usable(),
            default       => $query->where('status', $filters['status']),
        };

        return $query->orderBy('created_at')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Summaries
    |--------------------------------------------------------------------------
    | Deliberately few numbers. A report page with fourteen tiles is a page
    | nobody reads; four is a page somebody acts on.
    */

    /** @return array<int,array{label:string,value:string,tone:string,hint?:string}> */
    public function summary(ReportType $report, array $filters): array
    {
        return match ($report) {
            ReportType::Reservations => $this->reservationsSummary($filters),
            ReportType::Visitors     => $this->visitorsSummary($filters),
            ReportType::Payments     => $this->paymentsSummary($filters),
            ReportType::Vouchers     => $this->vouchersSummary($filters),
        };
    }

    private function reservationsSummary(array $filters): array
    {
        $rows = $this->reservationsQuery($filters)->get();

        $settled = $rows->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Completed]);

        return [
            [
                'label' => 'Reservations',
                'value' => number_format($rows->count()),
                'tone'  => 'primary',
                'hint'  => $settled->count() . ' confirmed or completed',
            ],
            [
                'label' => 'Guests expected',
                'value' => number_format((int) $settled->sum('participants')),
                'tone'  => 'info',
                'hint'  => 'Across confirmed visits only',
            ],
            [
                'label' => 'Billed',
                'value' => 'BDT ' . number_format($settled->sum(fn (Reservation $r) => $r->payableTotal())),
                'tone'  => 'success',
            ],
            [
                'label' => 'Still outstanding',
                'value' => 'BDT ' . number_format($settled->sum(fn (Reservation $r) => $r->outstandingTotal())),
                'tone'  => 'warning',
                'hint'  => 'On confirmed visits in this window',
            ],
        ];
    }

    private function visitorsSummary(array $filters): array
    {
        $rows = $this->visitors($filters);

        $returning = $rows->filter(fn (object $row) => $row->visits > 1);

        return [
            [
                'label' => 'Visitors',
                'value' => number_format($rows->count()),
                'tone'  => 'primary',
                'hint'  => 'People with a visit in this window',
            ],
            [
                'label' => 'Came more than once',
                'value' => number_format($returning->count()),
                'tone'  => 'success',
                'hint'  => $rows->count() > 0
                    ? round($returning->count() / $rows->count() * 100) . '% of them'
                    : null,
            ],
            [
                'label' => 'Guests',
                'value' => number_format((int) $rows->sum('participants')),
                'tone'  => 'info',
            ],
            [
                'label' => 'Received',
                'value' => 'BDT ' . number_format($rows->sum('paid')),
                'tone'  => 'success',
            ],
        ];
    }

    private function paymentsSummary(array $filters): array
    {
        $rows = $this->paymentsQuery($filters)->get();

        /*
         | Voucher settlements are shown apart from the rest, and this is the
         | point of doing so: NO MONEY MOVED. A voucher redemption settles a
         | reservation without anything reaching the till, so adding it to a
         | cash figure would overstate income by exactly the value of every
         | coupon honoured. The studio was paid for the gift voucher when it was
         | sold — that sale is the income, not the redemption.
         */
        $cash    = $rows->reject(fn (PaymentTransaction $t) => $t->channel === PaymentChannel::Voucher);
        $voucher = $rows->filter(fn (PaymentTransaction $t) => $t->channel === PaymentChannel::Voucher);

        // As at now, not ranged: "what is still owed" is not a question about a
        // window, and answering it as one would hide older unpaid requests.
        $outstanding = Payment::where('status', PaymentStatus::Pending)
            ->get()
            ->sum(fn (Payment $payment) => $payment->outstanding());

        return [
            [
                'label' => 'Received',
                'value' => 'BDT ' . number_format($cash->sum(fn (PaymentTransaction $t) => (float) $t->amount)),
                'tone'  => 'success',
                'hint'  => $cash->count() . ' ' . str('receipt')->plural($cash->count()),
            ],
            [
                'label' => 'Settled by voucher',
                'value' => 'BDT ' . number_format($voucher->sum(fn (PaymentTransaction $t) => (float) $t->amount)),
                'tone'  => 'primary',
                'hint'  => 'No money moved',
            ],
            [
                'label' => 'Paid online',
                'value' => 'BDT ' . number_format(
                    $cash->filter(fn (PaymentTransaction $t) => $t->channel === PaymentChannel::Gateway)
                        ->sum(fn (PaymentTransaction $t) => (float) $t->amount)
                ),
                'tone'  => 'info',
                'hint'  => 'Through SSLCommerz',
            ],
            [
                'label' => 'Owed to the studio',
                'value' => 'BDT ' . number_format($outstanding),
                'tone'  => 'warning',
                'hint'  => 'All open requests, as at today',
            ],
        ];
    }

    private function vouchersSummary(array $filters): array
    {
        $rows = $this->vouchersQuery(['status' => 'all'] + $filters)->get();

        // Everything still spendable, whenever it was issued. A liability does
        // not belong to the month it was created in.
        $liability = Voucher::usable()->sum('value');

        return [
            [
                'label' => 'Issued',
                'value' => number_format($rows->count()),
                'tone'  => 'primary',
                'hint'  => 'BDT ' . number_format($rows->sum(fn (Voucher $v) => (float) $v->value)) . ' of value',
            ],
            [
                'label' => 'Gift vouchers',
                'value' => number_format($rows->where('type', VoucherType::Gift)->count()),
                'tone'  => 'info',
            ],
            [
                'label' => 'Café credit',
                'value' => number_format($rows->where('type', VoucherType::CafeCredit)->count()),
                'tone'  => 'success',
                'hint'  => 'Issued automatically on payment',
            ],
            [
                'label' => 'Outstanding liability',
                'value' => 'BDT ' . number_format((float) $liability),
                'tone'  => 'warning',
                'hint'  => 'Every usable code, not just this window',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CSV
    |--------------------------------------------------------------------------
    */

    /**
     * Feed rows to the writer, a chunk at a time.
     *
     * chunk(), not get(). The point of streaming the response is undone the
     * moment the whole result set is loaded to iterate it — see CsvStream.
     *
     * @param  Closure(array):void  $write
     */
    public function stream(ReportType $report, array $filters, Closure $write): void
    {
        if ($report === ReportType::Visitors) {
            // Already an in-memory aggregate; see visitors().
            $this->visitors($filters)->each(fn (object $row) => $write($this->visitorRow($row)));

            return;
        }

        $this->query($report, $filters)->chunk(self::CHUNK, function (Collection $rows) use ($report, $write) {
            foreach ($rows as $row) {
                $write(match ($report) {
                    ReportType::Reservations => $this->reservationRow($row),
                    ReportType::Payments     => $this->paymentRow($row),
                    ReportType::Vouchers     => $this->voucherRow($row),
                    default                  => [],
                });
            }
        });
    }

    /** Order must match ReportType::Reservations->csvHeaders(). */
    private function reservationRow(Reservation $r): array
    {
        return [
            $r->reference_code,
            $r->reserved_date->toDateString(),
            substr((string) $r->start_time, 0, 5),
            substr((string) $r->end_time, 0, 5),
            $r->title(),
            $r->participants,
            $r->status->label(),
            $r->user?->name,
            $r->user?->email,
            $r->user?->phone,
            $r->purposes->pluck('name')->join('; '),
            CsvStream::money($r->payableTotal()),
            CsvStream::money($r->amountPaid()),
            CsvStream::money($r->outstandingTotal()),
            $r->source?->value,
            $r->created_at?->toDateTimeString(),
            $r->approved_at?->toDateTimeString(),
            $r->confirmed_at?->toDateTimeString(),
        ];
    }

    /** Order must match ReportType::Visitors->csvHeaders(). */
    private function visitorRow(object $row): array
    {
        return [
            $row->user?->name,
            $row->user?->email,
            $row->user?->phone,
            $row->user?->whatsapp,
            $row->visits,
            $row->participants,
            CsvStream::money($row->billed),
            CsvStream::money($row->paid),
            $row->user?->total_reservations,
            $row->user?->created_at?->toDateString(),
            $row->user?->last_reservation_at?->toDateString(),
        ];
    }

    /** Order must match ReportType::Payments->csvHeaders(). */
    private function paymentRow(PaymentTransaction $t): array
    {
        return [
            $t->reference,
            $t->received_at?->toDateTimeString(),
            $t->payment?->reference,
            $t->payment?->reservation?->reference_code,
            $t->payment?->reservation?->user?->name,
            $t->payment?->reservation?->user?->email,
            $t->channel->label(),
            $t->method->label(),
            CsvStream::money($t->amount),
            CsvStream::money($t->balance_after),
            $t->external_reference,
            $t->recordedBy?->name,
        ];
    }

    /** Order must match ReportType::Vouchers->csvHeaders(). */
    private function voucherRow(Voucher $v): array
    {
        return [
            $v->code,
            $v->type->label(),

            // displayStatus(), not the raw column: an Active voucher three
            // months past its date is Expired to everybody looking at it, and a
            // report that says Active would have somebody honouring it.
            $v->displayStatus(),
            CsvStream::money($v->value),
            $v->created_at?->toDateString(),
            $v->valid_from?->toDateString(),
            $v->expires_at?->toDateString(),
            $v->issued_to_name,
            $v->issued_to_email,
            $v->workshop?->title,
            $v->reservation?->reference_code,
            $v->redeemed_at?->toDateTimeString(),
            $v->redeemedForReservation?->reference_code,
            $v->redeemedBy?->name,
        ];
    }
}
