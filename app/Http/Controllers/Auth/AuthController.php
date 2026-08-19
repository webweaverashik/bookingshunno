<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\LoginActivity;
use App\Models\Auth\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    /**
     * The only thing a failed sign-in says.
     *
     * Deliberately vague about which half was wrong, and deliberately identical
     * for an unknown address, a deleted account, a deactivated one and a bad
     * password. See the block in login() for why.
     */
    private const LOGIN_FAILED = 'Those details did not work. Please check them and try again.';

    /**
     * A real bcrypt hash of a string nobody holds, used to spend the same time
     * on an unknown address as on a real one. Hard-coded rather than generated
     * per request because generating it would itself cost a different amount of
     * time, which is the problem it exists to solve.
     */
    private const TIMING_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    /*
    |--------------------------------------------------------------------------
    | Login screen
    |--------------------------------------------------------------------------
    */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login')->with('warning', 'Please login first.');
    }

    /*
    |--------------------------------------------------------------------------
    | Step 1 — verify credentials, then send an OTP (no session login yet)
    |--------------------------------------------------------------------------
    */
    public function login(Request $request, OtpService $otp)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        /*
        |----------------------------------------------------------------------
        | One answer for every kind of failure
        |----------------------------------------------------------------------
        | This block used to give four distinguishable replies: "No user found!"
        | (401), "This account is invalid or deleted." (403), "Account is
        | inactive." (403) and "User or password is incorrect." (401). Between
        | them they answered three questions for anybody who could type an email
        | address into a form:
        |
        |   does this person have an account here
        |   is it still active
        |   was it deleted
        |
        | That is a staff roster, readable by anyone, one address at a time. For
        | a business this size the roster is close to the whole team, and
        | knowing who is on it is the first step of every phishing attempt aimed
        | at the panel.
        |
        | Now: one message, one status code, one code path. The real reason goes
        | to the log, where the studio can read it and an attacker cannot.
        |
        | THE COST, stated plainly: a member of staff whose account has been
        | deactivated no longer sees why. That is a real loss of helpfulness and
        | it is worth paying here, because staff are a small known group who can
        | be told directly, while the form is open to the entire internet.
        */
        $user = User::withTrashed()->where('email', $request->email)->first();

        $failure = match (true) {
            ! $user                                          => 'unknown email',
            $user->trashed()                                 => 'account deleted',
            ! $user->is_active                               => 'account inactive',
            ! Hash::check($request->password, $user->password) => 'wrong password',
            default                                          => null,
        };

        if ($failure !== null) {
            /*
             | TIMING.
             |
             | Without this, a missing account returns before any hashing
             | happens while a real one pays for a full bcrypt comparison. The
             | difference is tens of milliseconds and it is measurable over a
             | handful of requests — which hands back, as a stopwatch reading,
             | exactly the answer the shared message above refuses to give.
             |
             | So an unknown address is charged the same work. The hash is a
             | real bcrypt digest of a string nobody has; it will never match,
             | and matching is not the point — spending the time is.
             */
            if (! $user) {
                Hash::check($request->password, self::TIMING_HASH);
            }

            Log::info('Failed staff login', [
                'email'  => $request->email,
                'reason' => $failure,
                'ip'     => $request->ip(),
            ]);

            return response()->json(['message' => self::LOGIN_FAILED], 401);
        }

        // OTP disabled — complete the login immediately.
        if (! config('otp.enabled', true)) {
            $this->completeLogin($request, $user);

            return response()->json([
                'message'  => 'Login successful!',
                'redirect' => route('admin.dashboard'),
            ]);
        }

        // Issue and email the code.
        try {
            $otp->generateAndSend($user);
        } catch (TransportExceptionInterface $e) {
            Log::error('Login OTP mail failed: ' . $e->getMessage());
            $otp->clear($user);

            return response()->json([
                'message' => 'We could not send your verification code right now. Please try again shortly.',
            ], 422);
        }

        // Remember which user is mid-login. This rides on the guest session.
        $request->session()->put('otp_user_id', $user->id);

        return response()->json([
            'message'  => 'A verification code has been sent to your email.',
            'redirect' => route('login.otp'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | OTP entry screen
    |--------------------------------------------------------------------------
    */
    public function showOtp(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        if (! config('otp.enabled', true)) {
            return redirect()->route('login');
        }

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->with('warning', 'Please sign in first.');
        }

        return view('auth.otp', [
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Step 2 — verify the OTP, log in, and terminate previous sessions
    |--------------------------------------------------------------------------
    */
    public function verifyOtp(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return response()->json([
                'message'  => 'Your session expired. Please sign in again.',
                'redirect' => route('login'),
            ], 422);
        }

        $length    = (int) config('otp.length', 6);
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'regex:/^\d{' . $length . '}$/'],
        ], [
            'code.required' => 'Please enter the verification code.',
            'code.regex'    => "Please enter the {$length}-digit code.",
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $result = $otp->verify($user, $request->code);

        if (! $result['ok']) {
            $payload = ['message' => $result['message']];

            // Dead pending login — bounce the user back to the start.
            if (! empty($result['reset'])) {
                $request->session()->forget('otp_user_id');
                $payload['redirect'] = route('login');
                return response()->json($payload, 422);
            }

            return response()->json($payload, 422);
        }

        /*
        | OTP verified — establish the authenticated session.
        */
        $this->completeLogin($request, $user);
        $request->session()->forget('otp_user_id');

        return response()->json([
            'message'  => 'Login successful!',
            'redirect' => route('admin.dashboard'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resend the OTP (throttled)
    |--------------------------------------------------------------------------
    */
    public function resendOtp(Request $request, OtpService $otp)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return response()->json([
                'message'  => 'Your session expired. Please sign in again.',
                'redirect' => route('login'),
            ], 422);
        }

        $wait = $otp->secondsUntilResend($user);

        if ($wait > 0) {
            return response()->json([
                'message' => "Please wait {$wait} second(s) before requesting a new code.",
                'wait' => $wait,
            ], 429);
        }

        try {
            $otp->generateAndSend($user);
        } catch (TransportExceptionInterface $e) {
            Log::error('Login OTP resend failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'We could not send your verification code right now. Please try again shortly.',
            ], 422);
        }

        return response()->json([
            'message' => 'A new verification code has been sent to your email.',
            'wait'    => (int) config('otp.resend_after', 60),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** Establish the authenticated session, enforce single-session, log the activity. */
    private function completeLogin(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();

        // Terminate every other session belonging to this user so only the
        // current one stays active.
        $this->terminateOtherSessions($user, $request->session()->getId());

        LoginActivity::create([
            'user_id'    => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'device'     => $this->detectDevice($request->header('User-Agent')),
        ]);
    }

    /** Resolve the active (non-deleted) user from the pending-login session key. */
    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('otp_user_id');

        if (! $userId) {
            return null;
        }

        return User::where('id', $userId)->where('is_active', true)->first();
    }

    /** Delete all sessions for this user except the current one (database driver). */
    private function terminateOtherSessions(User $user, string $currentSessionId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);
        $visible         = mb_substr($name, 0, 2);
        $masked          = str_repeat('*', max(1, mb_strlen($name) - 2));

        return $visible . $masked . '@' . $domain;
    }

    private function detectDevice($userAgent)
    {
        if (strpos((string) $userAgent, 'Mobile') !== false) {
            return 'Mobile';
        } elseif (strpos((string) $userAgent, 'Tablet') !== false) {
            return 'Tablet';
        }

        return 'Desktop';
    }
}
