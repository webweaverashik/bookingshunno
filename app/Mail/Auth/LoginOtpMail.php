<?php

namespace App\Mail\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Deliberately NOT ShouldQueue. The login controller needs to know whether the
 * send failed so it can say so, rather than leaving someone staring at an OTP
 * screen for a code that was never dispatched. Every other mailable in the
 * system (Phase 11) should be queued.
 */
class LoginOtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $code,
        public string $name,
        public int $expiresIn,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . config('app.name') . ' verification code',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.login-otp');
    }
}
