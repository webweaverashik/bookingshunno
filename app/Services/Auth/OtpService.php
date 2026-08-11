<?php

namespace App\Services\Auth;

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email one-time passwords for sign-in.
 *
 * Changes against the version reviewed earlier, each fixing a real problem:
 *
 *  - The `config('mail.default') === 'log' ? 'smtp' : null` override is gone.
 *    It forced SMTP whenever the log driver was active, so with the project's
 *    current .env (MAIL_MAILER=log, no credentials) every send threw and login
 *    was impossible locally.
 *  - Mail is still sent synchronously, not queued. The controller catches the
 *    transport failure and tells the visitor the code could not be sent;
 *    queuing would make that silent and leave them waiting for an email that
 *    never arrives.
 *  - The resend throttle is enforced here rather than only in the controller,
 *    so a future caller cannot turn this into an email cannon aimed at a real
 *    person's inbox.
 *  - Attempts are counted cumulatively as well as per code. Previously
 *    generateAndSend() reset attempts to zero, so the cap was five guesses,
 *    wait sixty seconds, five more, forever.
 */
class OtpService
{
    public const SENT              = 'sent';
    public const THROTTLED         = 'throttled';
    public const TOO_MANY_RESENDS  = 'too_many_resends';

    /**
     * Issue a code and email it. Returns one of the constants above; the caller
     * decides what to tell the user.
     */
    public function generateAndSend(User $user, bool $isResend = false): string
    {
        $existing = LoginOtp::firstWhere('user_id', $user->id);

        if ($isResend && $existing) {
            if ($this->secondsUntilResend($user) > 0) {
                return self::THROTTLED;
            }

            if ($existing->resend_count >= (int) config('otp.max_resends')) {
                return self::TOO_MANY_RESENDS;
            }
        }

        $length    = (int) config('otp.length', 6);
        $expiresIn = (int) config('otp.expires_in', 5);
        $code      = $this->randomCode($length);

        LoginOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code'           => Hash::make($code),
                'expires_at'     => now()->addMinutes($expiresIn),
                'attempts'       => 0,
                'total_attempts' => $existing->total_attempts ?? 0,
                'resend_count'   => $isResend ? (($existing->resend_count ?? 0) + 1) : 0,
                'last_sent_at'   => now(),
            ],
        );

        Mail::to($user->email)->send(new LoginOtpMail($code, $user->name, $expiresIn));

        return self::SENT;
    }

    /**
     * Verify a submitted code.
     *
     * @return array{ok: bool, reset: bool, message: string}
     *         reset => the pending login is dead; send the user back to /login
     */
    public function verify(User $user, string $code): array
    {
        $otp = LoginOtp::firstWhere('user_id', $user->id);

        if (! $otp) {
            return $this->fail(true, 'No active verification code. Please sign in again.');
        }

        if ($otp->isExpired()) {
            return $this->fail(false, 'That code has expired. Request a new one.');
        }

        $max      = (int) config('otp.max_attempts', 5);
        $maxTotal = (int) config('otp.max_total_attempts', 12);

        if ($otp->attempts >= $max || $otp->total_attempts >= $maxTotal) {
            $otp->delete();

            return $this->fail(true, 'Too many incorrect attempts. Please sign in again.');
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');
            $otp->increment('total_attempts');
            $otp->refresh();

            if ($otp->attempts >= $max || $otp->total_attempts >= $maxTotal) {
                $otp->delete();

                return $this->fail(true, 'Too many incorrect attempts. Please sign in again.');
            }

            $remaining = max(0, $max - $otp->attempts);

            return $this->fail(false, "Incorrect code. {$remaining} attempt(s) remaining.");
        }

        $otp->delete();

        return ['ok' => true, 'reset' => false, 'message' => 'Verified.'];
    }

    public function secondsUntilResend(User $user): int
    {
        $otp = LoginOtp::firstWhere('user_id', $user->id);

        if (! $otp || ! $otp->last_sent_at) {
            return 0;
        }

        $after   = (int) config('otp.resend_after', 60);
        $elapsed = now()->getTimestamp() - $otp->last_sent_at->getTimestamp();

        return max(0, $after - $elapsed);
    }

    public function clear(User $user): void
    {
        LoginOtp::where('user_id', $user->id)->delete();
    }

    /** Housekeeping for the scheduler; abandoned logins otherwise pile up. */
    public function pruneExpired(): int
    {
        return LoginOtp::where('expires_at', '<', now()->subHour())->delete();
    }

    private function fail(bool $reset, string $message): array
    {
        return ['ok' => false, 'reset' => $reset, 'message' => $message];
    }

    /**
     * random_int, not rand(). Leading zeros are preserved because the code is
     * built as a string — 048213 must stay six characters or it can never be
     * entered successfully.
     */
    private function randomCode(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }

        return $code;
    }
}
