<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Admin\Concerns\RendersReservations;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * PHASE 12A — the payments register, plus the two actions that move money.
 *
 * RendersReservations is pulled in for one reason: requesting a payment happens
 * from the reservation drawer, so store() has to hand back a refreshed
 * reservation list and drawer exactly as the decision endpoints do. Everything
 * else here renders payments.
 *
 * No sorting/filtering trait of its own. RendersReservations exists because two
 * controllers needed identical filter parsing; nothing else renders payments,
 * so extracting one would be an abstraction with a single caller.
 */
class PaymentController extends Controller
{
    use RendersReservations;

    /**
     * Whitelisted sort columns. A user-supplied string never reaches orderBy()
     * — it is a column name, so it cannot be bound as a parameter and
     * validation elsewhere would not save it.
     *
     * PREFIXED, and it has to stay prefixed. RendersReservations declares its
     * own SORTABLE and PAGE_SIZES, and since PHP 8.2 a trait's constants are
     * class constants: a controller that uses the trait and declares constants
     * of the same name with different values is a fatal composition error, not
     * a shadow. Anything else that both uses this trait and needs its own
     * whitelist has the same problem.
     */
    private const PAYMENT_SORTABLE = [
        'reference' => 'reference',
        'due'       => 'due_at',
        'amount'    => 'amount_due',
        'paid'      => 'amount_paid',
        'status'    => 'status',
        'created'   => 'created_at',
    ];

    private const PAYMENT_PAGE_SIZES = [25, 50, 100];

    public function __construct(private readonly PaymentService $payments)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payment::class);

        $payments = $this->query($request);

        return view('admin.payments.index', [
            'payments'  => $payments,
            'filters'   => $this->filters($request),
            'pageSizes' => self::PAYMENT_PAGE_SIZES,
            'summary'   => $this->summary(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);

        return response()->json([
            'success' => true,
            'data'    => $this->listPayload($request),
        ]);
    }

    public function show(Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        return response()->json([
            'success' => true,
            'data'    => [
                'html'      => $this->detailHtml($payment),
                'reference' => $payment->reference,

                // Fills the record-payment modal. The outstanding figure is
                // computed here rather than in the browser so the form cannot
                // offer a ceiling the server would then reject.
                'record'    => [
                    'outstanding' => round($payment->outstanding(), 2),
                    'url'         => route('admin.payments.record', $payment),
                    'cancel_url'  => route('admin.payments.cancel', $payment),
                    'can_record'  => Gate::allows('record', $payment),
                    'can_cancel'  => Gate::allows('cancel', $payment),
                ],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Requesting payment — called from the reservation drawer
    |--------------------------------------------------------------------------
    */

    /**
     * The figures for the request modal, as data rather than markup.
     *
     * The modal's layout is in Blade; this supplies the numbers for both
     * payment types so switching between them needs no round trip and no
     * arithmetic in the browser. §17 keeps business logic off the client — a
     * split percentage is business logic, so it is calculated here.
     */
    public function create(Reservation $reservation): JsonResponse
    {
        Gate::authorize('requestPayment', $reservation);

        return response()->json([
            'success' => true,
            'data'    => [
                'reference' => $reservation->reference_code,
                'visitor'   => $reservation->user?->name,
                'url'       => route('admin.payments.store', $reservation),
            ] + $this->payments->preview($reservation),
        ]);
    }

    public function store(StorePaymentRequest $request, Reservation $reservation): JsonResponse
    {
        Gate::authorize('requestPayment', $reservation);

        try {
            $payment = $this->payments->request(
                $reservation,
                $request->paymentType(),
                $request->user(),
                $request->deadlineHours(),
                $request->validated()['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            // Almost always a race: someone else requested payment, or decided
            // the reservation, while this drawer sat open. 409 rather than 422
            // — nothing is wrong with what was submitted.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                '%s requested: BDT %s, due %s. Reference %s.',
                $payment->type->describe($payment->percentage),
                number_format((float) $payment->amount_due),
                $payment->due_at->format('j M, g:i A'),
                $payment->reference,
            ),
            'data' => [
                // PHASE 12B replaces this with "the visitor has been emailed".
                'emailed' => false,
                'list'    => $this->reservationListPayload($request),
                'detail'  => $this->reservationDetailHtml($reservation->refresh()),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Settling
    |--------------------------------------------------------------------------
    */

    public function record(RecordPaymentRequest $request, Payment $payment): JsonResponse
    {
        Gate::authorize('record', $payment);

        try {
            $payment = $this->payments->record(
                $payment,
                (float) $request->validated()['amount'],
                $request->method(),
                $request->user(),
                $request->validated()['reference'] ?? null,
                $request->paidAt(),
                $request->validated()['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        $settled = $payment->status === PaymentStatus::Paid;

        return response()->json([
            'success' => true,
            'message' => $settled
                ? "{$payment->reference} is settled in full."
                : sprintf(
                    'Recorded. BDT %s still outstanding on %s.',
                    number_format($payment->outstanding()),
                    $payment->reference,
                ),
            'data' => [
                'html' => $this->detailHtml($payment),
                'list' => $this->listPayload($request),
            ],
        ]);
    }

    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        Gate::authorize('cancel', $payment);

        $reason = trim((string) $request->input('reason'));

        // Required, and validated here rather than in a Form Request: it is one
        // field on an otherwise empty payload, and the reason ends up in the
        // reservation's permanent history where "cancelled" with no explanation
        // is worse than useless.
        if ($reason === '' || mb_strlen($reason) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors'  => ['reason' => ['Say why this request is being withdrawn.']],
            ], 422);
        }

        try {
            $payment = $this->payments->cancel($payment, $request->user(), $reason);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => "{$payment->reference} has been withdrawn. The reservation is back to Approved.",
            'data'    => [
                'html' => $this->detailHtml($payment),
                'list' => $this->listPayload($request),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function query(Request $request): LengthAwarePaginator
    {
        $filters = $this->filters($request);

        $query = Payment::query()
            ->with(['reservation.user:id,name,email,phone', 'requestedBy:id,name', 'recordedBy:id,name'])
            ->search($filters['q']);

        match ($filters['status']) {
            'all'     => null,
            'open'    => $query->open(),
            'overdue' => $query->overdue(),
            default   => $query->where('status', $filters['status']),
        };

        $query->orderBy(self::PAYMENT_SORTABLE[$filters['sort']], $filters['dir']);

        // Stable second key. Without it, two requests created in the same second
        // can swap places between page 1 and page 2 and a row appears twice or
        // not at all.
        return $query
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /**
     * @return array{q:string,status:string,sort:string,dir:string,per_page:int}
     */
    private function filters(Request $request): array
    {
        $status  = (string) $request->query('status', 'open');
        $sort    = (string) $request->query('sort', '');
        $dir     = strtolower((string) $request->query('dir', ''));
        $perPage = (int) $request->query('per_page', 25);

        $allowed = array_merge(
            ['all', 'open', 'overdue'],
            array_column(PaymentStatus::cases(), 'value'),
        );

        return [
            'q'        => trim((string) $request->query('q', '')),
            'status'   => in_array($status, $allowed, true) ? $status : 'open',
            'sort'     => array_key_exists($sort, self::PAYMENT_SORTABLE) ? $sort : 'due',

            // Soonest deadline first by default: the register's job is to show
            // what needs chasing, and that is the oldest end of the list.
            'dir'      => in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc',
            'per_page' => in_array($perPage, self::PAYMENT_PAGE_SIZES, true) ? $perPage : 25,
        ];
    }

    /** @return array{html:string,total:int} */
    private function listPayload(Request $request): array
    {
        $payments = $this->query($request);

        return [
            'html' => view('admin.payments.partials.list', [
                'payments' => $payments,
                'filters'  => $this->filters($request),
            ])->render(),
            'total' => $payments->total(),
        ];
    }

    private function detailHtml(Payment $payment): string
    {
        // PHASE 12B: transactions.recordedBy, because the drawer now lists every
        // receipt. Eager-loaded rather than lazy — a part-paid request with
        // several receipts would otherwise fire a query per row.
        $payment->load([
            'reservation.user', 'reservation.items',
            'requestedBy', 'recordedBy',
            'transactions.recordedBy',
        ]);

        return view('admin.payments.partials.detail', ['payment' => $payment])->render();
    }

    /**
     * The three figures across the top of the register.
     *
     * Deliberately unfiltered — they describe the studio's position, not the
     * current search. A total that changed every time someone typed in the
     * search box would be a different number every time it was read aloud.
     *
     * @return array<string,mixed>
     */
    private function summary(): array
    {
        return [
            'outstanding' => (float) Payment::open()->sum('amount_due')
                - (float) Payment::open()->sum('amount_paid'),
            'overdue'     => Payment::overdue()->count(),
            'collected'   => (float) Payment::where('status', PaymentStatus::Paid)->sum('amount_paid'),
        ];
    }
}
