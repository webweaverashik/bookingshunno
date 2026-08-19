<?php

namespace App\Http\Controllers\Admin\Visitor;

use App\Enums\Reservation\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Visitor\VisitorRequest;
use App\Models\Auth\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Visitors are rows in the same users table as staff, separated by role.
 *
 * Unlike workshops and blocked dates, this list grows without bound, so the
 * whole-table-swap pattern gains server-side search and pagination here. The
 * shape is the one Phase 9's reservation list will reuse: filters go up as
 * query parameters, the rendered list comes back as HTML in the envelope, and
 * the JavaScript swaps one container.
 *
 * Deliberately no policy class. A policy on App\Models\Auth\User would also
 * govern staff-user management later and the two have different permissions
 * (visitors.* against users.*), so authorisation is by permission name here —
 * checked by route middleware and again in the views.
 */
class VisitorController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        return view('admin.visitors.index', [
            'visitors' => $this->query($request),
            'filters'  => $this->filters($request),
            'stats'    => $this->stats(),
        ]);
    }

    /** List container only — used for search, filtering and paging. */
    public function list(Request $request): JsonResponse
    {
        $visitors = $this->query($request);

        return response()->json([
            'success' => true,
            'data'    => [
                'html'  => view('admin.visitors.partials.list', [
                    'visitors' => $visitors,
                    'filters'  => $this->filters($request),
                ])->render(),
                'total' => $visitors->total(),
            ],
        ]);
    }

    /**
     * Profile and full reservation history, rendered server-side and dropped
     * into the drawer. Returning HTML rather than JSON keeps the status badges,
     * money formatting and date formatting in Blade where the rest of the admin
     * panel already defines them.
     */
    public function show(User $visitor): JsonResponse
    {
        abort_unless($visitor->isVisitor(), 404);

        $visitor->load([
            'reservations.items',
            'reservations.purposes',

            // For the "Paid to date" card. amountPaid() reads the payments
            // COLLECTION and deliberately does not lazy-load, so it has to be
            // eager-loaded here or the figure silently reads zero.
            'reservations.payments',
        ]);

        $confirmed = $visitor->reservations->whereIn('status', [
            ReservationStatus::Confirmed,
            ReservationStatus::Completed,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'html' => view('admin.visitors.partials.detail', [
                    'visitor'   => $visitor,
                    'lifetime'  => $confirmed->sum(fn ($r) => (float) $r->total_amount),

                    /*
                     | What this visitor has actually HANDED OVER, across every
                     | reservation they have ever made.
                     |
                     | Not the same figure as lifetime value and the difference
                     | is the point: lifetime value is what their confirmed
                     | visits were worth, this is what has been received. A 50%
                     | booking fee makes them differ by half; an unpaid balance
                     | or a voucher redemption makes them differ by more. Both
                     | are worth seeing side by side before somebody rings a
                     | visitor about money.
                     |
                     | Counted across ALL reservations, not just confirmed ones:
                     | money received against a booking later cancelled was
                     | still received, and hiding it would understate what the
                     | studio owes back.
                     */
                    'paid'      => $visitor->reservations->sum(fn ($r) => $r->amountPaid()),
                    'attended'  => $visitor->reservations
                        ->where('status', ReservationStatus::Completed)->count(),
                    'cancelled' => $visitor->reservations
                        ->whereIn('status', [ReservationStatus::Cancelled, ReservationStatus::NoShow])
                        ->count(),
                ])->render(),
                'name' => $visitor->name,
            ],
        ]);
    }

    /** Populates the edit modal. */
    public function edit(User $visitor): JsonResponse
    {
        abort_unless($visitor->isVisitor(), 404);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $visitor->id,
                'name'       => $visitor->name,
                'email'      => $visitor->email,
                'phone'      => $visitor->phone,
                'whatsapp'   => $visitor->whatsapp,
                'is_active'  => $visitor->is_active,
                'update_url' => route('admin.visitors.update', $visitor->id),
            ],
        ]);
    }

    public function update(VisitorRequest $request, User $visitor): JsonResponse
    {
        abort_unless($visitor->isVisitor(), 404);

        // Only the contact fields. Role, password, source and the reservation
        // counters are never editable from here — the counters are derived, and
        // changing a role from the visitor screen would be a way to grant
        // admin access without touching user management.
        $visitor->update($request->safe()->only([
            'name', 'email', 'phone', 'whatsapp', 'is_active',
        ]));

        return $this->listResponse($request, "{$visitor->name}'s details have been updated.");
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function query(Request $request): LengthAwarePaginator
    {
        $filters = $this->filters($request);

        return User::query()
            ->visitors()
            ->search($filters['q'])
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($filters['status'] === 'returning', fn ($q) => $q->where('total_reservations', '>', 1))
            ->when($filters['status'] === 'never', fn ($q) => $q->where('total_reservations', 0))

            // Ranged on when the account was created — "who joined us in
            // August". Either end may be left open.
            ->when($filters['joined_from'] !== '', fn ($q) => $q->whereDate('created_at', '>=', $filters['joined_from']))
            ->when($filters['joined_to'] !== '', fn ($q) => $q->whereDate('created_at', '<=', $filters['joined_to']))

            ->orderByDesc('last_reservation_at')
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /** @return array{q:string,status:string,joined_from:string,joined_to:string} */
    private function filters(Request $request): array
    {
        $status = (string) $request->query('status', 'all');

        return [
            'q'      => trim((string) $request->query('q', '')),
            // Whitelisted rather than passed through: the value reaches a query
            // builder and a Blade selected() check.
            'status' => in_array($status, ['all', 'active', 'inactive', 'returning', 'never'], true)
                ? $status
                : 'all',

            // Shape-checked here. They are bound as values by the query
            // builder, so a malformed one is a wrong answer rather than a
            // danger — but a wrong answer nobody can see is worse than none.
            'joined_from' => $this->date($request->query('joined_from')),
            'joined_to'   => $this->date($request->query('joined_to')),
        ];
    }

    /** A Y-m-d string, or empty. Anything else is not a date and is discarded. */
    private function date(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private function stats(): array
    {
        return [
            'total'     => User::query()->visitors()->count(),
            'returning' => User::query()->visitors()->where('total_reservations', '>', 1)->count(),
            'thisMonth' => User::query()->visitors()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    private function listResponse(Request $request, string $message): JsonResponse
    {
        $visitors = $this->query($request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => [
                'html'  => view('admin.visitors.partials.list', [
                    'visitors' => $visitors,
                    'filters'  => $this->filters($request),
                ])->render(),
                'total' => $visitors->total(),
            ],
        ]);
    }
}
