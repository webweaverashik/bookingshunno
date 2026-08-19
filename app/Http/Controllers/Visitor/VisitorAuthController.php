<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Auth\LoginActivity;
use App\Models\Auth\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * How a returning visitor gets in.
 *
 * NO PASSWORD. Not a shortcut — a visitor account has never had a password its
 * owner could know. ReservationService::resolveVisitor() creates one with a
 * random string the moment somebody submits their first request, precisely so
 * the column is never null, and nobody has ever been sent it. A password form
 * here would be a door with no key.
 *
 * The email IS the account. It is what the reservation was filed under, where
 * the approval went, and where the payment link went — so proving control of
 * it proves as much as any password would, and rather more than a password
 * that had been emailed out in plain text.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT THE STAFF LOGIN
 * ---------------------------------------------------------------------------
 * Two rules, both load-bearing:
 *
 *   1. STAFF ARE REFUSED HERE. An Admin's account is protected by a password
 *      AND a code. If a staff email could be typed into this form, that becomes
 *      a code alone — the OTP stops being a second factor and becomes the only
 *      one. Anybody who knows an admin's address would need only to reach their
 *      inbox. So a staff address gets the same nothing-happened screen as an
 *      address we have never seen, and no code is sent.
 *
 *   2. SEPARATE SESSION KEY. The staff flow parks its half-finished login under
 *      'otp_user_id'. This one uses 'visitor_otp_user_id'. Sharing the key
 *      would let a code issued by one flow be redeemed by the other's verify
 *      endpoint, which is the same hole as (1) reached by a different door.
 *
 * ---------------------------------------------------------------------------
 * WHY EVERY ANSWER IS THE SAME ANSWER
 * ---------------------------------------------------------------------------
 * sendCode() responds identically whether the address is a live visitor, a
 * staff member, or nothing at all. The alternative — "no account found" —
 * turns this form into a tool for asking whether a given person has ever been
 * to the studio, which is a question about somebody's private life that we have
 * no business answering to anyone who can type.
 *
 * The verify screen is therefore reachable with no pending user, and any code
 * typed into it is simply wrong. That costs one wasted screen for somebody who
 * mistyped their address, and it is worth it.
 *
 * (The staff login still answers "No user found!" and is a Phase 17 item. This
 * endpoint is public and new, so it is built closed rather than left to be
 * fixed later.)
 */
class VisitorAuthController extends Controller
{
    /** Where the half-finished login lives. Deliberately not 'otp_user_id'. */
    private const PENDING_USER  = 'visitor_otp_user_id';
    private const PENDING_EMAIL = 'visitor_otp_email';

    public function __construct(private readonly OtpService $otp)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Step one — ask for the address
    |--------------------------------------------------------------------------
    */

    public function showSignIn(): View|RedirectResponse
    {
        if ($redirect = $this->bounceIfSignedIn()) {
            return $redirect;
        }

        return view('visitor.auth.sign-in');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        if ($redirect = $this->bounceIfSignedIn()) {
            return $redirect;
        }

        $validated = $request->validate(
            ['email' => ['required', 'string', 'email', 'max:255']],
            ['email.email' => 'That does not look like an email address.'],
        );

        $email = strtolower(trim($validated['email']));

        // Remembered whether or not an account exists, because the verify
        // screen has to look the same either way.
        $request->session()->put(self::PENDING_EMAIL, $email);
        $request->session()->forget(self::PENDING_USER);

        $user = $this->eligibleVisitor($email);

        if ($user) {
            try {
                $this->otp->generateAndSend($user);
            } catch (TransportExceptionInterface $e) {
                /*
                 | The one place the generic answer breaks, and knowingly.
                 |
                 | A mail transport failure is a fact about our SMTP server, not
                 | about this address — an attacker cannot make it fire for one
                 | account and not another. Staying silent here would leave a
                 | real visitor waiting on a code that was never dispatched,
                 | which is a worse trade than the sliver this leaks.
                 */
                Log::error('Visitor sign-in code failed to send: ' . $e->getMessage());
                $this->otp->clear($user);

                return back()->with(
                    'visitor_error',
                    'We could not send the code just now. Please try again in a few minutes, or message us on WhatsApp.'
                );
            }

            $request->session()->put(self::PENDING_USER, $user->id);
        }

        return redirect()->route('visitor.verify');
    }

    /*
    |--------------------------------------------------------------------------
    | Step two — the code
    |--------------------------------------------------------------------------
    */

    public function showVerify(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->bounceIfSignedIn()) {
            return $redirect;
        }

        $email = $request->session()->get(self::PENDING_EMAIL);

        if (! $email) {
            return redirect()->route('visitor.login');
        }

        return view('visitor.auth.verify', [
            'maskedEmail' => $this->maskEmail($email),
            'length'      => (int) config('otp.length', 6),
            'resendAfter' => (int) config('otp.resend_after', 60),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        if ($redirect = $this->bounceIfSignedIn()) {
            return $redirect;
        }

        if (! $request->session()->get(self::PENDING_EMAIL)) {
            return redirect()->route('visitor.login');
        }

        $length = (int) config('otp.length', 6);

        $request->validate(
            ['code' => ['required', 'regex:/^\d{' . $length . '}$/']],
            [
                'code.required' => 'Please enter the code we sent you.',
                'code.regex'    => "The code is {$length} digits.",
            ],
        );

        $user = $this->pendingUser($request);

        /*
         | No pending user means the address was never one of ours — or was a
         | staff address, which this endpoint treats the same way. Answered with
         | the ordinary wrong-code message, because saying anything else here is
         | how the enumeration defence above gets undone at the last step.
         */
        if (! $user) {
            return back()->with('visitor_error', 'That code is not right, or it has expired.');
        }

        $result = $this->otp->verify($user, $request->input('code'));

        if (! $result['ok']) {
            if (! empty($result['reset'])) {
                $this->forgetPending($request);

                return redirect()->route('visitor.login')
                    ->with('visitor_error', $result['message']);
            }

            return back()->with('visitor_error', $result['message']);
        }

        /*
         | Re-checked after verification, not only before it. A code lives for
         | five minutes, and an account can be deactivated inside five minutes.
         */
        if (! $this->eligibleVisitor($user->email)) {
            $this->forgetPending($request);

            return redirect()->route('visitor.login')
                ->with('visitor_error', 'We could not sign you in. Please get in touch and we will help.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $this->forgetPending($request);

        /*
         | NO terminateOtherSessions() here.
         |
         | Single-session enforcement is a staff control: it exists so a shared
         | admin account cannot be open on the counter machine and someone's
         | phone at once. Applying it to visitors would sign a person out of
         | their laptop because they opened their own booking on their phone,
         | which protects nobody and reads as a bug.
         */
        LoginActivity::create([
            'user_id'    => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'device'     => $this->detectDevice($request->header('User-Agent')),
        ]);

        return redirect()->intended(route('visitor.index'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($redirect = $this->bounceIfSignedIn()) {
            return $redirect;
        }

        if (! $request->session()->get(self::PENDING_EMAIL)) {
            return redirect()->route('visitor.login');
        }

        $user = $this->pendingUser($request);

        // Same shape of answer as a real resend. Nothing was sent.
        if (! $user) {
            return back()->with('visitor_notice', 'If that address is on our records, a new code is on its way.');
        }

        $wait = $this->otp->secondsUntilResend($user);

        if ($wait > 0) {
            return back()->with('visitor_error', "Please wait {$wait} more second(s) before asking for another code.");
        }

        try {
            $this->otp->generateAndSend($user);
        } catch (TransportExceptionInterface $e) {
            Log::error('Visitor sign-in code failed to resend: ' . $e->getMessage());

            return back()->with('visitor_error', 'We could not send the code just now. Please try again shortly.');
        }

        return back()->with('visitor_notice', 'A new code is on its way.');
    }

    /*
    |--------------------------------------------------------------------------
    | Out
    |--------------------------------------------------------------------------
    */

    public function signOut(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('visitor_notice', 'You are signed out.');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * The only kind of account this door opens.
     *
     * Active, holding the Visitor role, and NOT staff. The last clause is not
     * redundant: a member of staff who also books workshops could legitimately
     * hold both roles, and for them the password requirement wins.
     */
    private function eligibleVisitor(string $email): ?User
    {
        $user = User::where('email', $email)->where('is_active', true)->first();

        if (! $user || $user->isStaff() || ! $user->isVisitor()) {
            return null;
        }

        return $user;
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PENDING_USER);

        if (! $id) {
            return null;
        }

        $user = User::where('id', $id)->where('is_active', true)->first();

        return $user && ! $user->isStaff() ? $user : null;
    }

    private function forgetPending(Request $request): void
    {
        $request->session()->forget([self::PENDING_USER, self::PENDING_EMAIL]);
    }

    /** Staff go to the panel, signed-in visitors to their visits. */
    private function bounceIfSignedIn(): ?RedirectResponse
    {
        if (! Auth::check()) {
            return null;
        }

        return Auth::user()->isStaff()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('visitor.index');
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);

        return mb_substr($name, 0, 2) . str_repeat('*', max(1, mb_strlen($name) - 2)) . '@' . $domain;
    }

    private function detectDevice(?string $userAgent): string
    {
        if (str_contains((string) $userAgent, 'Mobile')) {
            return 'Mobile';
        }

        if (str_contains((string) $userAgent, 'Tablet')) {
            return 'Tablet';
        }

        return 'Desktop';
    }
}
