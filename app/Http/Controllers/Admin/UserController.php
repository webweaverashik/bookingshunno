<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * PHASE 20 — staff accounts.
 *
 * STAFF ONLY. Visitors have their own module from Phase 8, and the two must not
 * merge: a visitor record is somebody who booked a workshop, a staff record is
 * somebody who can approve one. Every query here is scoped by ROLE rather than
 * by "not a visitor", so a role added later cannot quietly fall into this list
 * and hand somebody the panel.
 *
 * ---------------------------------------------------------------------------
 * THE THREE GUARDS, AND WHY EACH ONE EXISTS
 * ---------------------------------------------------------------------------
 * Every destructive action here can lock the studio out of its own system, so
 * each is refused rather than warned about:
 *
 *   1. YOU CANNOT ACT ON YOURSELF. No deactivating, deleting or demoting your
 *      own account. The failure is immediate and total — you lose the session
 *      you would need to undo it.
 *
 *   2. THE LAST ACTIVE ADMIN IS PROTECTED. Deactivating, deleting or demoting
 *      them leaves a system with settings, gateway credentials and approvals
 *      that nobody can reach. A Manager cannot promote anyone, so there is no
 *      way back short of a database edit.
 *
 *   3. DEACTIVATING KILLS THE SESSION. Otherwise the account keeps working for
 *      up to a fortnight — and someone is usually deactivated precisely because
 *      their access should stop now.
 */
class UserController extends Controller
{
    private const PER_PAGE = 20;

    /** The only roles this screen manages. */
    private const STAFF_ROLES = ['Admin', 'Manager'];

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => $this->query($request)->paginate(self::PER_PAGE)->withQueryString(),
            'roles' => Role::whereIn('name', self::STAFF_ROLES)->pluck('name'),
            'stats' => $this->stats(),
        ]);
    }

    /** The table half, for the AJAX refresh. Blade renders, JS swaps. */
    public function list(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'html' => view('admin.users.partials.list', [
                    'users' => $this->query($request)->paginate(self::PER_PAGE)->withQueryString(),
                ])->render(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create and edit
    |--------------------------------------------------------------------------
    */

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'],

                /*
                 | Marked as created in the panel, not on the web. The source
                 | column is what tells a staff account apart from a visitor
                 | record that happens to hold a role, and the visitor register
                 | filters on it.
                 */
                'source'   => ReservationSource::Admin,

                /*
                 | Verified on creation. A staff account is vouched for by the
                 | Admin who made it — there is nobody to send a confirmation
                 | link to who is not already in the room.
                 */
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$data['role']]);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => "{$user->name} can now sign in. Give them the password in person or over a channel you trust — it is not emailed.",
        ], 201);
    }

    /** The form's current values, for the edit modal. Never the password. */
    public function edit(User $user): JsonResponse
    {
        $this->assertStaff($user);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'phone'     => $user->phone,
                'whatsapp'  => $user->whatsapp,
                'role'      => $user->getRoleNames()->first(),
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->assertStaff($user);

        $data  = $request->validated();
        $isMe  = $user->id === $request->user()->id;
        $role  = $data['role'];

        /*
         | Demoting the last Admin is refused for the same reason deleting them
         | is: a Manager cannot promote anybody, so the system would be left
         | with no route back to its own settings.
         */
        if ($user->isAdmin() && $role !== 'Admin' && $this->isLastActiveAdmin($user)) {
            return $this->refuse('This is the only active Admin. Promote somebody else first.');
        }

        if ($isMe && $role !== 'Admin' && $user->isAdmin()) {
            return $this->refuse('You cannot remove your own Admin role.');
        }

        if ($isMe && ! $data['is_active']) {
            return $this->refuse('You cannot deactivate your own account.');
        }

        if (! $data['is_active'] && $user->isAdmin() && $this->isLastActiveAdmin($user)) {
            return $this->refuse('This is the only active Admin. Activate another one first.');
        }

        DB::transaction(function () use ($user, $data, $role) {
            $user->fill([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'] ?? null,
                'whatsapp'  => $data['whatsapp'] ?? null,
                'is_active' => $data['is_active'],
            ]);

            // Blank means unchanged. Same rule as every other credential field
            // in this panel — an empty box is not an instruction to wipe it.
            if (filled($data['password'] ?? null)) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();
            $user->syncRoles([$role]);
        });

        if (! $user->is_active) {
            $this->terminateSessions($user);
        }

        return response()->json(['success' => true, 'message' => "{$user->name} updated."]);
    }

    /*
    |--------------------------------------------------------------------------
    | Activation and removal
    |--------------------------------------------------------------------------
    */

    public function toggle(Request $request, User $user): JsonResponse
    {
        $this->assertStaff($user);

        if ($user->id === $request->user()->id) {
            return $this->refuse('You cannot deactivate your own account.');
        }

        $activating = ! $user->is_active;

        if (! $activating && $user->isAdmin() && $this->isLastActiveAdmin($user)) {
            return $this->refuse('This is the only active Admin. Activate another one first.');
        }

        $user->update(['is_active' => $activating]);

        /*
         | Sessions die immediately on deactivation.
         |
         | is_active is checked at sign-in, but a session outlives that check by
         | two weeks — and somebody is deactivated precisely because their access
         | should stop now, not at the end of the fortnight.
         */
        if (! $activating) {
            $this->terminateSessions($user);
        }

        return response()->json([
            'success' => true,
            'message' => $activating
                ? "{$user->name} can sign in again."
                : "{$user->name} is deactivated and has been signed out everywhere.",
            'data'    => ['is_active' => $activating],
        ]);
    }

    /**
     * Soft delete.
     *
     * Soft, and it has to be: this user is the author of reservation decisions,
     * payment records and voucher issues, all of which point at their id. A hard
     * delete would either break those foreign keys or blank the name against
     * every decision they ever made, and an audit trail that forgets who
     * approved a booking is not an audit trail.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->assertStaff($user);

        if ($user->id === $request->user()->id) {
            return $this->refuse('You cannot delete your own account.');
        }

        if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
            return $this->refuse('This is the only active Admin. The studio would be locked out.');
        }

        $name = $user->name;

        $user->update(['is_active' => false]);
        $this->terminateSessions($user);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "{$name} removed. Their name stays on past decisions, which is deliberate.",
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function query(Request $request)
    {
        $query = User::query()
            ->role(self::STAFF_ROLES)
            ->with(['roles:id,name', 'latestLoginActivity'])
            ->search($request->query('q'));

        match ($request->query('status')) {
            'active'   => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            default    => null,
        };

        if (in_array($request->query('role'), self::STAFF_ROLES, true)) {
            $query->role($request->query('role'));
        }

        return $query->orderBy('name');
    }

    private function stats(): array
    {
        $staff = User::role(self::STAFF_ROLES);

        return [
            'total'    => (clone $staff)->count(),
            'active'   => (clone $staff)->where('is_active', true)->count(),
            'admins'   => User::role('Admin')->where('is_active', true)->count(),
            'managers' => User::role('Manager')->where('is_active', true)->count(),
        ];
    }

    /**
     * Would removing this user leave nobody able to reach the settings screen?
     *
     * Counts ACTIVE Admins other than this one. Deleted users are excluded by
     * the model's soft-delete scope, which is what makes this correct rather
     * than merely close: a soft-deleted Admin still holds the role and would
     * otherwise be counted as cover that does not exist.
     */
    private function isLastActiveAdmin(User $user): bool
    {
        return User::role('Admin')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    /** 404 rather than 403 — this endpoint does not manage visitors at all. */
    private function assertStaff(User $user): void
    {
        abort_unless($user->isStaff(), 404);
    }

    private function terminateSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }

    private function refuse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 422);
    }
}
