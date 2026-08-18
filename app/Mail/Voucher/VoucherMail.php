<?php

namespace App\Mail\Voucher;

use App\Enums\Communication\ReservationMailKind;
use App\Listeners\Communication\LogMailDelivery;
use App\Models\Voucher\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * PHASE 14A — a voucher's own Mailable.
 *
 * Separate from ReservationNotificationMail, and this is the one place where a
 * second Mailable earns itself. That class requires a Reservation for its
 * subject line, its reply-to and its templates; a gift voucher has no
 * reservation at all — somebody bought it as a present before anyone booked
 * anything. Making the reservation nullable there would put a null check into
 * six templates to serve one case.
 *
 * What IS shared is deliberate: the same theme, the same correlation header, so
 * a voucher email looks like every other studio email and shows up in the same
 * communications log.
 */
class VoucherMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** Untyped — Illuminate\Mail\Mailable declares $theme without a type. */
    public $theme = 'shunno';

    public function __construct(
        public Voucher $voucher,
        public ?int $communicationId = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ReservationMailKind::VoucherIssued->subject(
                $voucherCode = $this->voucher->code,
                config('app.name'),
            ),

            // Replies go to the studio, not to a no-reply address. Somebody
            // asking "can I use this on a Tuesday" should reach a person.
            replyTo: [config('shunno.contact.email')],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.vouchers.issued',
            with: [
                'voucher' => $this->voucher,
                'contact' => config('shunno.contact'),
            ],
        );
    }

    public function headers(): Headers
    {
        if ($this->communicationId === null) {
            return new Headers();
        }

        return new Headers(text: [
            LogMailDelivery::HEADER => (string) $this->communicationId,
        ]);
    }
}
