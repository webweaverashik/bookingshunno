<?php
namespace App\Services\Auth;

use App\Mail\LoginOtpMail;
use App\Models\Auth\LoginOtp;
use App\Models\Auth\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    /**
     * Generate a fresh code, persist its hash and email it to the user.
     * Any previous code for the user is overwritten.
     */
    public function generateAndSend(User $user): void
    {
        $length    = (int) config('otp.length', 6);
        $expiresIn = (int) config('otp.expires_in', 5);
        $code      = $this->randomCode($length);

        LoginOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code'         => Hash::make($code),
                'expires_at'   => now()->addMinutes($expiresIn),
                'attempts'     => 0,
                'last_sent_at' => now(),
            ]
        );

        $mailer = config('mail.default') === 'log' ? 'smtp' : null; // null = use the default
        Mail::mailer($mailer)->to($user->email)->send(new LoginOtpMail($code, $user->name, $expiresIn));

        // Mail::to($user->email)->send(new LoginOtpMail($code, $user->name, $expiresIn));
    }

    /**
     * Verify a submitted code against the stored hash.
     *
     * Returns:
     *   ok      => bool   verification passed
     *   reset   => bool   the pending login is dead; force the user back to /login
     *   message => string user-facing message
     */
    public function verify(User $user, string $code): array
    {
        $otp = LoginOtp::where('user_id', $user->id)->first();

        if (! $otp) {
            return ['ok' => false, 'reset' => true, 'message' => 'No active verification code. Please sign in again.'];
        }

        if ($otp->isExpired()) {
            return ['ok' => false, 'reset' => false, 'message' => 'The verification code has expired. Please request a new one.'];
        }

        $max = (int) config('otp.max_attempts', 5);

        if ($otp->attempts >= $max) {
            $otp->delete();
            return ['ok' => false, 'reset' => true, 'message' => 'Too many incorrect attempts. Please sign in again.'];
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');
            $remaining = max(0, $max - $otp->attempts);

            if ($remaining === 0) {
                $otp->delete();
                return ['ok' => false, 'reset' => true, 'message' => 'Too many incorrect attempts. Please sign in again.'];
            }

            return ['ok' => false, 'reset' => false, 'message' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        $otp->delete();
        return ['ok' => true, 'reset' => false, 'message' => 'Verified.'];
    }

    /**
     * Seconds the user still has to wait before a resend is allowed (0 = allowed now).
     */
    public function secondsUntilResend(User $user): int
    {
        $otp = LoginOtp::where('user_id', $user->id)->first();

        if (! $otp || ! $otp->last_sent_at) {
            return 0;
        }

        $after   = (int) config('otp.resend_after', 60);
        $elapsed = now()->getTimestamp() - $otp->last_sent_at->getTimestamp();

        return max(0, $after - $elapsed);
    }

    /**
     * Remove any pending code for the user.
     */
    public function clear(User $user): void
    {
        LoginOtp::where('user_id', $user->id)->delete();
    }

    private function randomCode(int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
}
