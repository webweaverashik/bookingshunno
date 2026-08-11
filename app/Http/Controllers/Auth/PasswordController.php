<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Always the same answer, whether or not the address exists — the previous
     * "We could not find an active account with that email" was an enumeration
     * oracle open to anyone.
     */
    private const NEUTRAL_RESPONSE = 'If that address belongs to an account that uses a password, a reset link is on its way.';

    public function showLinkRequest(): View
    {
        return view('auth.password.email');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);

        $user = User::firstWhere('email', $request->string('email')->lower()->toString());

        // Password resets are for staff. A visitor has no usable password and
        // signs in by OTP; sending them a reset link would be confusing at best.
        $eligible = $user && $user->is_active && $user->hasUsablePassword();

        if ($eligible) {
            $status = Password::sendResetLink(['email' => $user->email]);

            if ($status !== Password::RESET_LINK_SENT) {
                Log::warning('Password reset link not sent', ['email' => $user->email, 'status' => $status]);
            }
        } else {
            Log::info('Password reset requested for ineligible address', [
                'email'  => $request->input('email'),
                'reason' => match (true) {
                    ! $user                      => 'no such user',
                    ! $user->is_active           => 'inactive',
                    ! $user->hasUsablePassword() => 'visitor account, OTP only',
                    default                      => 'unknown',
                },
            ]);
        }

        return back()->with('success', self::NEUTRAL_RESPONSE);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.password.reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'        => $password,
                    'password_set_at' => now(),
                    'remember_token'  => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Your password has been reset. Please sign in.')
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => 'That reset link is invalid or has expired. Please request a new one.']);
    }
}
