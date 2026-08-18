<?php

namespace App\Http\Controllers\Admin\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Staff\UpdatePasswordRequest;
use App\Http\Requests\Admin\Staff\UpdateProfileRequest;
use App\Models\Auth\LoginActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * PHASE 17 — a staff member's own account.
 *
 * Scoped to $request->user() throughout, with no id anywhere in a route or a
 * payload. That is not a convenience: an endpoint that took a user id would be
 * one authorisation check away from letting any Manager change any Admin's
 * password, and this module has no user-management screen to hang such a check
 * on. There is nothing to get wrong if there is nothing to pass.
 *
 * No permission gate on the routes either, for the same reason — every signed-in
 * member of staff may edit themselves and only themselves.
 */
class ProfileController extends Controller
{
    /**
     * How much history the page carries.
     *
     * Thirty, and it is a real cap rather than a page size. DataTables sorts
     * and pages CLIENT-side, which means every row it is given is in the DOM —
     * fine for thirty, not fine for the thousands an account accumulates over a
     * year. The project ruled DataTables out of the reservation register for
     * exactly that reason; a bounded list is the case where it fits.
     *
     * Thirty is also about as far back as anyone can usefully recognise their
     * own sign-ins. Beyond that, "was that me?" stops having an answer.
     */
    private const ACTIVITY_LIMIT = 30;

    public function index(Request $request): View
    {
        return view('admin.profile.index', [
            'user'       => $request->user(),
            'activities' => LoginActivity::where('user_id', $request->user()->id)
                ->latest('id')
                ->limit(self::ACTIVITY_LIMIT)
                ->get(),
            'limit'      => self::ACTIVITY_LIMIT,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Details
    |--------------------------------------------------------------------------
    */

    /**
     * Name, phone, WhatsApp — and email, which is the interesting one.
     *
     * Changing the email address moves the account: it is the sign-in
     * identifier, where the OTP goes, and where a password reset would go. An
     * open session that could change it silently would turn a borrowed laptop
     * into a full account takeover, so UpdateProfileRequest demands the current
     * password whenever the address differs. Everything else saves freely.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $emailChanged = strtolower($data['email']) !== strtolower($user->email);

        $user->fill([
            'name'     => $data['name'],
            'email'    => strtolower($data['email']),
            'phone'    => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => $emailChanged
                ? 'Saved. You will sign in with the new address from now on.'
                : 'Your details are updated.',
            'data'    => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Password
    |--------------------------------------------------------------------------
    */

    /**
     * Change the password, then sign every other session out.
     *
     * The second half is the point. Somebody changing their password has often
     * just realised a session is open somewhere it should not be — a shared
     * machine, a lost phone, a browser at an internet café. Leaving those alive
     * makes the change cosmetic, since the attacker's session is already
     * authenticated and never needs the password again.
     *
     * Laravel's logoutOtherDevices() is deliberately not used: it rehashes the
     * password to rotate the remember token, and this application already
     * tracks sessions in the database for the single-session rule, so the same
     * delete used at login does the job with no second hash.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill(['password' => Hash::make($request->validated()['password'])])->save();

        $terminated = $this->terminateOtherSessions($user, $request->session()->getId());

        // The current session survives, but its id is rotated — the credential
        // behind it has just changed, and reusing the old id would leave the
        // pre-change identifier valid.
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => $terminated > 0
                ? "Password changed, and {$terminated} other " . str('session')->plural($terminated) . ' signed out.'
                : 'Password changed.',
        ]);
    }

    /** @return int how many were closed */
    private function terminateOtherSessions($user, string $currentSessionId): int
    {
        if (config('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
