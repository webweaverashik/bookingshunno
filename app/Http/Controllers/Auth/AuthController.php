<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AuthController extends Controller
{
    /**
     * One message for every credential failure.
     *
     * The previous version returned four distinguishable outcomes — "No user
     * found", "invalid or deleted", "inactive", "user or password is
     * incorrect" — which let anyone test an address and learn whether that
     * person is a Shunno customer. Tolerable on an internal system; not on a
     * public booking site. The specific reason is logged instead.
     */
    private const GENERIC_FAILURE = 'Those details do not match our records.';

    public function __construct(private readonly OtpService $otp)
    {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            LoginActivity::record('locked', $request, null, $credentials['email']);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $user = User::firstWhere('email', $credentials['email']);

        // hasUsablePassword() first: visitor accounts carry an unusable hash and
        // no password_set_at, and passing a null hash to Hash::check() is a
        // deprecation on PHP 8.3 and a TypeError beyond it.
        $valid = $user
            && $user->is_active
            && $user->hasUsablePassword()
            && Hash::check($credentials['password'], $user->password);

        if (! $valid) {
            RateLimiter::hit($throttleKey, 300);
            LoginActivity::record('failed', $request, $user, $credentials['email']);

            Log::info('Failed sign-in', [
                'email'  => $credentials['email'],
                'reason' => match (true) {
                    ! $user                       => 'no such user',
                    ! $user->is_active            => 'inactive',
                    ! $user->hasUsablePassword()  => 'no usable password (visitor account)',
                    default                       => 'wrong password',
                },
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => self::GENERIC_FAILURE]);
        }

        RateLimiter::clear($throttleKey);

        // Regenerate before the OTP step, not only after it. Otherwise the
        // pending-login session id is one an attacker could have fixated.
        $request->session()->regenerate();

        if (! config('otp.staff.enabled')) {
            return $this->completeLogin($request, $user);
        }

        try {
            $this->otp->generateAndSend($user);
        } catch (TransportExceptionInterface $e) {
            $this->otp->clear($user);
            Log::error('OTP send failed', ['user' => $user->id, 'message' => $e->getMessage()]);

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'We could not send your verification code. Please try again shortly.',
            ]);
        }

        $request->session()->put('otp_user_id', $user->id);
        $request->session()->put('otp_started_at', now()->timestamp);

        LoginActivity::record('otp_sent', $request, $user);

        return redirect()->route('otp.show');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your sign-in timed out. Please start again.',
            ]);
        }

        return view('auth.otp', [
            'email'            => Str::mask($user->email, '*', 2, max(strpos($user->email, '@') - 3, 1)),
            'secondsUntilResend' => $this->otp->secondsUntilResend($user),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your sign-in timed out. Please start again.',
            ]);
        }

        $request->validate([
            // String with a digits rule, never integer: random_int can produce
            // a leading zero and casting 048213 to an int makes it unenterable.
            'code' => ['required', 'string', 'regex:/^\d{' . config('otp.length', 6) . '}$/'],
        ], [
            'code.regex' => 'Enter the ' . config('otp.length', 6) . '-digit code from your email.',
        ]);

        $result = $this->otp->verify($user, $request->string('code')->toString());

        if (! $result['ok']) {
            LoginActivity::record('otp_failed', $request, $user);

            if ($result['reset']) {
                $this->forgetPendingLogin($request);

                return redirect()->route('login')->withErrors(['email' => $result['message']]);
            }

            return back()->withErrors(['code' => $result['message']]);
        }

        return $this->completeLogin($request, $user);
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your sign-in timed out. Please start again.',
            ]);
        }

        try {
            $status = $this->otp->generateAndSend($user, isResend: true);
        } catch (TransportExceptionInterface $e) {
            Log::error('OTP resend failed', ['user' => $user->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['code' => 'We could not send another code. Please try again shortly.']);
        }

        return match ($status) {
            OtpService::THROTTLED => back()->withErrors([
                'code' => 'Please wait ' . $this->otp->secondsUntilResend($user) . ' seconds before asking for another code.',
            ]),
            OtpService::TOO_MANY_RESENDS => tap(
                redirect()->route('login')->withErrors(['email' => 'Too many codes requested. Please sign in again.']),
                fn () => $this->forgetPendingLogin($request),
            ),
            default => back()->with('success', 'A new code is on its way.'),
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user) {
            LoginActivity::record('logout', $request, $user);
        }

        return redirect()->route('login')->with('success', 'You have been signed out.');
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();
        $this->forgetPendingLogin($request);

        $user->forceFill(['last_login_at' => now()])->save();
        LoginActivity::record('login', $request, $user);

        // Single-session enforcement applies to staff only. Doing it to
        // visitors would sign them out on the laptop the moment they checked a
        // booking on their phone.
        if ($user->isStaff()) {
            $this->terminateOtherSessions($request, $user);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    private function terminateOtherSessions(Request $request, User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    /**
     * The half-finished login, if it is still alive. A pending login expires on
     * its own so it cannot sit in the session for the full session lifetime
     * being resent from.
     */
    private function pendingUser(Request $request): ?User
    {
        $id      = $request->session()->get('otp_user_id');
        $started = $request->session()->get('otp_started_at');

        if (! $id || ! $started) {
            return null;
        }

        if (now()->timestamp - (int) $started > config('otp.pending_login_ttl', 15) * 60) {
            $this->forgetPendingLogin($request);

            return null;
        }

        $user = User::find($id);

        if (! $user || ! $user->is_active) {
            $this->forgetPendingLogin($request);

            return null;
        }

        return $user;
    }

    private function forgetPendingLogin(Request $request): void
    {
        $request->session()->forget(['otp_user_id', 'otp_started_at']);
    }
}
