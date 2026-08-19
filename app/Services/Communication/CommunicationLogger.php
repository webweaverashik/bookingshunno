<?php

namespace App\Services\Communication;

use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\ReservationMailKind;
use App\Mail\Reservation\ReservationNotificationMail;
use App\Mail\Voucher\VoucherMail;
use App\Models\Auth\User;
use App\Models\Communication\Communication;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use App\Models\Reservation\Reservation;
use App\Models\Voucher\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Writes the log row, then sends the email.
 *
 * That order matters. The row is created first so its id can be stamped into
 * the message as a header, which is what lets LogMailDelivery match a delivery
 * back to a record. Sending first and logging afterwards would leave every
 * message uncorrelatable and would lose the record entirely whenever the send
 * itself threw.
 *
 * One class owns both the first send and the resend, so a resent payment
 * request cannot drift from the original in wording, recipient or attachments.
 */
class CommunicationLogger
{
    /**
     * Log and dispatch. Returns the row, whatever happened to the send.
     *
     * @param  string|array<int,string>  $to
     */
    public function send(
        string|array $to,
        Reservation $reservation,
        ReservationMailKind $kind,
        ?string $note = null,
        ?Payment $payment = null,
        ?PaymentTransaction $transaction = null,
        ?User $triggeredBy = null,
        ?Communication $resendOf = null,
    ): Communication {
        $recipient = is_array($to) ? implode(', ', $to) : $to;

        $log = new Communication();

        $log->forceFill([
            'to_email'       => mb_substr($recipient, 0, 255),
            'subject'        => $kind->subject($reservation->reference_code, config('app.name')),
            'kind'           => $kind->value,
            'reservation_id' => $reservation->id,
            'payment_id'     => $payment?->id,
            'transaction_id' => $transaction?->id,
            'note'           => $note,
            'status'         => CommunicationStatus::Queued,
            'queued_at'      => now(),
            'triggered_by'   => $triggeredBy?->id,
            'is_resend'      => $resendOf !== null,

            // A resend of a resend still points at the first message, so the
            // drawer groups the whole chain under one original rather than
            // nesting.
            'resend_of'      => $resendOf?->resend_of ?? $resendOf?->id,
        ])->save();

        try {
            Mail::to($to)->queue(
                new ReservationNotificationMail($reservation, $kind, $note, $payment, $transaction, $log->id)
            );
        } catch (Throwable $e) {
            /*
             | Reached when the queue itself is unreachable, or when
             | QUEUE_CONNECTION=sync and the transport fails. The reservation is
             | already saved and the admin has been told their action worked,
             | which it did — only the email did not. Recording that here is the
             | whole point of this phase: previously it was a log line nobody
             | would ever look at.
             */
            $log->forceFill([
                'status' => CommunicationStatus::Failed,
                'error'  => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::error('Reservation notification failed to dispatch.', [
                'reservation'   => $reservation->reference_code,
                'kind'          => $kind->value,
                'communication' => $log->id,
                'error'         => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * The voucher email.
     *
     * Its own method rather than a branch in send(), because a voucher may have
     * no reservation behind it at all and send() builds its subject and its
     * context from one. Everything else is identical: row first, id stamped
     * into the header, same failure handling, same log.
     */
    public function sendVoucher(Voucher $voucher, ?User $triggeredBy = null): ?Communication
    {
        if (! $voucher->issued_to_email) {
            return null;        // Nobody to write to. Not an error.
        }

        $log = new Communication();

        $log->forceFill([
            'to_email'       => mb_substr($voucher->issued_to_email, 0, 255),
            'subject'        => ReservationMailKind::VoucherIssued->subject($voucher->code, config('app.name')),
            'kind'           => ReservationMailKind::VoucherIssued->value,

            // Set for café credit, null for a gift voucher bought outright.
            // Either way it is the visit that EARNED the coupon, never one it
            // was later spent on.
            'reservation_id' => $voucher->reservation_id,

            'note'           => $voucher->code,
            'status'         => CommunicationStatus::Queued,
            'queued_at'      => now(),
            'triggered_by'   => $triggeredBy?->id,
        ])->save();

        try {
            Mail::to($voucher->issued_to_email)->queue(new VoucherMail($voucher, $log->id));
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => CommunicationStatus::Failed,
                'error'  => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::error('Voucher email failed to dispatch.', [
                'voucher' => $voucher->code,
                'error'   => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Send the same message again.
     *
     * Rebuilt from the stored context rather than copied, so the visitor gets a
     * message generated by today's templates — a wording correction the client
     * asked for should reach somebody who needs the email resent, not only
     * people who had not been written to yet.
     *
     * What does NOT change is the payment being referred to. A payment request
     * resend points at the same Payment row, so the amount, the deadline and
     * the link are the ones the visitor was originally quoted, even if the
     * reservation has been edited since.
     *
     * @throws RuntimeException when the message cannot or should not be repeated.
     */
    public function resend(Communication $original, User $actor): Communication
    {
        if (! $original->isResendable()) {
            throw new RuntimeException('This message cannot be resent.');
        }

        if (! $original->canResendNow()) {
            throw new RuntimeException(
                'This was already resent in the last few minutes. Give it a moment before trying again.'
            );
        }

        $reservation = $original->reservation;
        $email       = $reservation?->user?->email;

        if (! $email) {
            throw new RuntimeException('There is no email address on this reservation to send to.');
        }

        $kind = $original->mailKind();

        // A payment request whose payment has since been withdrawn would send a
        // live link to a dead request. Better to say so than to send it.
        if ($original->payment && ! $original->payment->isOpen()
            && $kind === ReservationMailKind::PaymentRequested) {
            throw new RuntimeException(
                "That payment request is {$original->payment->status->label()}. Create a new one instead of resending this."
            );
        }

        return $this->send(
            $email,
            $reservation,
            $kind,
            $original->note,
            $original->payment,
            $original->transaction,
            $actor,
            $original,
        );
    }
}
