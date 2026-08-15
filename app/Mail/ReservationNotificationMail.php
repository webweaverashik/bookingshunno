<?php

namespace App\Mail;

use App\Enums\ReservationMailKind;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 11 — every reservation notification.
 *
 * ShouldQueue, unlike LoginOtpMail. The OTP mail is deliberately synchronous
 * because the login controller has to know whether it failed; these are the
 * opposite case. An admin approving a reservation should not be left waiting on
 * an SMTP handshake, and a slow mail server must never be able to fail an
 * approval that has already been written to the database.
 *
 * QUEUE_CONNECTION is `database` and the jobs table exists, so this works as
 * soon as a worker is running. With QUEUE_CONNECTION=sync it still sends —
 * just inside the request — which is fine for local testing and wrong for
 * production.
 *
 * The Reservation is serialised by ID (SerializesModels), so the worker reloads
 * it fresh. That is the right behaviour here: if an admin corrects a party size
 * thirty seconds after approving, the email that goes out should carry the
 * corrected figure.
 */
class ReservationNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * PHASE 12C — the Shunno mail theme.
     *
     * Set here rather than in config/mail.php so it applies to reservation mail
     * only. LoginOtpMail is a functional message that should stay plain, and
     * anything Laravel itself sends — a failed-job notification, a password
     * reset — has no business wearing the studio's identity.
     *
     * Requires resources/views/vendor/mail/html/themes/shunno.css, which means
     * the mail views must have been published:
     *
     *     php artisan vendor:publish --tag=laravel-mail
     */
    public $theme = 'shunno';

    /**
     * @param  string|null  $note  The decision note — the reason for a decline,
     *                             the question being asked, what the Admin is
     *                             being asked to decide. Passed in rather than
     *                             read from the status history so the email
     *                             cannot pick up a later entry, and so a
     *                             template never has to guess which row it
     *                             wants.
     */
    public function __construct(
        public Reservation $reservation,
        public ReservationMailKind $kind,
        public ?string $note = null,

        /*
         | PHASE 12C. Null for every kind except the two payment ones, which
         | cannot be rendered without it — an amount, a deadline and a link do
         | not exist on a reservation.
         |
         | Optional rather than a second Mailable class because the alternative
         | is duplicating the envelope, the reply-to rule, the theme and the
         | queue behaviour, and then keeping two copies of them in step.
         */
        public ?Payment $payment = null,

        /*
         | The specific receipt this email is about. Passed rather than read as
         | "the latest" at send time: these are queued, and two payments landing
         | close together would otherwise both link to whichever arrived second.
         */
        public ?PaymentTransaction $transaction = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind->subject(
                $this->reservation->reference_code,
                config('app.name'),
            ),

            // Visitors reply to these. Without this, a reply goes to whatever
            // no-reply address MAIL_FROM_ADDRESS happens to be, and the studio
            // never sees it.
            replyTo: $this->kind->isInternal()
                ? []
                : [config('shunno.contact.email')],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: $this->kind->view(),
            with: [
                'reservation' => $this->reservation,
                'note'        => $this->note,
                'contact'     => config('shunno.contact'),
                'payment'     => $this->payment,
                'transaction' => $this->transaction,
            ],
        );
    }
}
