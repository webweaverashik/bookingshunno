<?php

namespace App\Http\Controllers\Admin\Voucher;

use App\Enums\Voucher\VoucherStatus;
use App\Enums\Voucher\VoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Voucher\StoreVoucherRequest;
use App\Models\Voucher\Voucher;
use App\Models\Workshop\Workshop;
use App\Services\Voucher\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * PHASE 14B — the voucher register, plus the counter workflow.
 *
 * The counter workflow is lookup() and it is the reason this module exists in
 * the shape it does. A visitor holding a coupon is standing in front of
 * somebody, and the question is "is this good, and for how much" — answered in
 * one step, before anything is spent. Making staff search the register, open a
 * drawer and read a status would be slower and would invite them to redeem the
 * wrong row.
 */
class VoucherController extends Controller
{
    private const VOUCHER_SORTABLE = [
        'code'    => 'code',
        'value'   => 'value',
        'expires' => 'expires_at',
        'status'  => 'status',
        'created' => 'created_at',
    ];

    private const VOUCHER_PAGE_SIZES = [25, 50, 100];

    public function __construct(private readonly VoucherService $vouchers)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Register
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Voucher::class);

        return view('admin.vouchers.index', [
            'vouchers'  => $this->query($request),
            'filters'   => $this->filters($request),
            'pageSizes' => self::VOUCHER_PAGE_SIZES,
            'workshops' => Workshop::orderBy('title')->get(['id', 'title']),
            'summary'   => $this->summary(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Voucher::class);

        return response()->json(['success' => true, 'data' => $this->listPayload($request)]);
    }

    public function show(Voucher $voucher): JsonResponse
    {
        Gate::authorize('view', $voucher);

        return response()->json([
            'success' => true,
            'data'    => ['html' => $this->detailHtml($voucher), 'code' => $voucher->code],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | The counter
    |--------------------------------------------------------------------------
    */

    /**
     * Look a code up without spending it.
     *
     * Deliberately separate from redeem(). Staff need to be able to say "yes,
     * that is 300 taka and it is good until the fourteenth" before committing
     * anything, and a lookup that redeemed as a side effect would burn a coupon
     * every time somebody mistyped.
     *
     * Answers 200 with usable:false rather than 404 for a code that exists but
     * cannot be used — the difference between "no such code" and "used last
     * Tuesday" is exactly what the person at the counter needs to hear.
     */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Voucher::class);

        $code = strtoupper(trim((string) $request->query('code')));

        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Enter a voucher code.'], 422);
        }

        $voucher = Voucher::with(['workshop:id,title', 'reservation:id,reference_code'])
            ->where('code', $code)
            ->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'No voucher with that code. Check the spelling — there are no letter O or number 0 in our codes.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'code'       => $voucher->code,
                'type'       => $voucher->type->label(),
                'value'      => (float) $voucher->value,
                'usable'     => $voucher->isRedeemable(),
                'reason'     => $voucher->unusableReason(),
                'status'     => $voucher->displayStatus(),
                'colour'     => $voucher->displayColour(),
                'spend_on'   => $voucher->type->spendableOn(),
                'expires'    => $voucher->expires_at?->format('j M Y'),
                'holder'     => $voucher->issued_to_name,
                'workshop'   => $voucher->workshop?->title,
                'can_redeem' => Gate::allows('redeem', $voucher),
                'redeem_url' => route('admin.vouchers.redeem', $voucher),
                'show_url'   => route('admin.vouchers.show', $voucher),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Writing
    |--------------------------------------------------------------------------
    */

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        Gate::authorize('create', Voucher::class);

        try {
            $voucher = $this->vouchers->issueGift($request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => $voucher->issued_to_email
                ? "{$voucher->code} created and emailed to {$voucher->issued_to_email}."
                : "{$voucher->code} created. Write the code down — it is not emailed to anyone.",
            'data' => [
                'code' => $voucher->code,
                'list' => $this->listPayload($request),
            ],
        ]);
    }

    public function redeem(Request $request, Voucher $voucher): JsonResponse
    {
        Gate::authorize('redeem', $voucher);

        try {
            $voucher = $this->vouchers->redeem(
                $voucher,
                $request->user(),
                null,   // Redeemed at the counter, not against a reservation.
                trim((string) $request->input('note')) ?: null,
            );
        } catch (RuntimeException $e) {
            // Expired, already used, or somebody else got there first. 409 —
            // nothing is wrong with the request, the voucher simply is not
            // spendable.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => sprintf(
                '%s redeemed — BDT %s.',
                $voucher->code,
                number_format((float) $voucher->value),
            ),
            'data' => [
                'html' => $this->detailHtml($voucher),
                'list' => $this->listPayload($request),
            ],
        ]);
    }

    public function cancel(Request $request, Voucher $voucher): JsonResponse
    {
        Gate::authorize('cancel', $voucher);

        $reason = trim((string) $request->input('reason'));

        if ($reason === '' || mb_strlen($reason) > 500) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors'  => ['reason' => ['Say why this voucher is being cancelled.']],
            ], 422);
        }

        try {
            $voucher = $this->vouchers->cancel($voucher, $request->user(), $reason);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'message' => "{$voucher->code} has been cancelled.",
            'data'    => [
                'html' => $this->detailHtml($voucher),
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

        $query = Voucher::query()
            ->with(['workshop:id,title', 'reservation:id,reference_code', 'issuedBy:id,name', 'redeemedBy:id,name'])
            ->search($filters['q']);

        match ($filters['status']) {
            'all'      => null,
            'usable'   => $query->usable(),

            // Active but past its date. Kept as its own filter because it is the
            // thing the studio will want to see when writing off liabilities,
            // and it is invisible under either "active" or "redeemed".
            'expired'  => $query->where('status', VoucherStatus::Active)
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', '<', now()),
            default    => $query->where('status', $filters['status']),
        };

        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        return $query
            ->orderBy(self::VOUCHER_SORTABLE[$filters['sort']], $filters['dir'])
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        $status  = (string) $request->query('status', 'usable');
        $type    = (string) $request->query('type', 'all');
        $sort    = (string) $request->query('sort', '');
        $dir     = strtolower((string) $request->query('dir', ''));
        $perPage = (int) $request->query('per_page', 25);

        $statuses = array_merge(
            ['all', 'usable', 'expired'],
            array_column(VoucherStatus::cases(), 'value'),
        );

        $types = array_merge(['all'], array_column(VoucherType::cases(), 'value'));

        return [
            'q'        => trim((string) $request->query('q', '')),
            'status'   => in_array($status, $statuses, true) ? $status : 'usable',
            'type'     => in_array($type, $types, true) ? $type : 'all',
            'sort'     => array_key_exists($sort, self::VOUCHER_SORTABLE) ? $sort : 'created',
            'dir'      => in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc',
            'per_page' => in_array($perPage, self::VOUCHER_PAGE_SIZES, true) ? $perPage : 25,
        ];
    }

    /** @return array{html:string,total:int} */
    private function listPayload(Request $request): array
    {
        $vouchers = $this->query($request);

        return [
            'html' => view('admin.vouchers.partials.list', [
                'vouchers' => $vouchers,
                'filters'  => $this->filters($request),
            ])->render(),
            'total' => $vouchers->total(),
        ];
    }

    private function detailHtml(Voucher $voucher): string
    {
        $voucher->load(['workshop', 'reservation.user', 'redeemedForReservation', 'issuedBy', 'redeemedBy']);

        return view('admin.vouchers.partials.detail', ['voucher' => $voucher])->render();
    }

    /**
     * Unfiltered, like the payments register's. These describe the studio's
     * position, not the current search — a figure that changed while somebody
     * typed would be a different number every time it was read aloud.
     *
     * @return array<string,mixed>
     */
    private function summary(): array
    {
        return [
            // What the studio still owes in vouchers. The one number an owner
            // actually wants from this screen.
            'outstanding' => (float) Voucher::usable()->sum('value'),
            'live'        => Voucher::usable()->count(),
            'redeemed'    => (float) Voucher::where('status', VoucherStatus::Redeemed)->sum('value'),
        ];
    }
}
