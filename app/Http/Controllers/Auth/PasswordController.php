<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordController extends Controller
{
/**
 * Show the "forgot password" form.
 */
    public function showLinkRequestForm()
    {
        return view('auth.password.email');
    }

    /**
     * Handle sending the reset link email.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'We could not find an active account with that email address.',
            ], 422);
        }

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );

            Log::info('Password reset status: ' . $status);

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Password reset link sent to your email.',
                ]);
            } else {
                // Consider checking for INVALID_USER here too if needed
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unable to send reset link. Please try again later.',
                ], 422);
            }
        } catch (TransportExceptionInterface $e) {
            Log::error('Mail sending failed: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'too many')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You can only request a password reset once every 60 seconds. Please check your email or try again later.',
                ], 429); // 429 Too Many Requests
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'The email address seems invalid or unreachable. Please check and try again.',
            ], 422);
        }
    }

    /**
     * Show the reset password form.
     */
    public function showResetForm(Request $request, $token = null)
    {
        $email = $request->email;

        if (! $token || ! $email) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid password reset link.');
        }

        // Find token row
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid password reset link.');
        }

        // Tokens in DB are hashed, so verify hash matches token
        if (! Hash::check($token, $record->token)) {
            return redirect()->route('password.request')
                ->with('error', 'Invalid password reset link.');
        }

        // Check expiration (default 60 minutes)
        $expires   = config('auth.passwords.users.expire', 60);
        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes($expires)->isPast()) {
            return redirect()->route('password.request')
                ->with('warning', 'This reset link has expired.');
        }

        // Valid token → show reset form
        return view('auth.password.reset', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Handle the actual password reset.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            // Success - return JSON success message
            return response()->json([
                'status'  => 'success',
                'message' => 'Your password has been reset successfully.',
            ]);
        }

        // Failure - invalid or expired token etc.
        return response()->json([
            'status'  => 'error',
            'message' => __($status),
        ], 422);
    }
}
