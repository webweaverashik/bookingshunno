<?php

namespace App\Mail;

use App\Enums\ReservationMailKind;
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
            ],
        );
    }
}
