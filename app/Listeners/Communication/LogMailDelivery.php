<?php

namespace App\Listeners\Communication;

use App\Enums\Communication\CommunicationStatus;
use App\Models\Communication\Communication;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Flips a logged email from Queued to Sent.
 *
 * Hooked to the mailer rather than to our own code, because the interesting
 * moment happens in the QUEUE WORKER, minutes after and in a different process
 * from the request that queued it. Nothing in the web request can know whether
 * SMTP eventually accepted the message.
 *
 * CORRELATION. The mailer knows nothing about our tables, so
 * ReservationNotificationMail stamps an X-Shunno-Communication header carrying
 * the row id and this reads it back. Matching on recipient and subject instead
 * would break the moment two identical emails went to one address, which is
 * exactly what a resend is.
 *
 * Failures are swallowed. This is bookkeeping about an email that has already
 * been sent successfully; letting it throw would fail the queue job and cause a
 * retry, sending the visitor a second copy of a message that arrived fine.
 */
class LogMailDelivery
{
    public const HEADER = 'X-Shunno-Communication';

    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->sent->getOriginalMessage();

            if (! method_exists($message, 'getHeaders')) {
                return;
            }

            $header = $message->getHeaders()->get(self::HEADER);

            if (! $header) {
                return;     // Not one of ours — an OTP, or a framework mail.
            }

            $id = (int) $header->getBodyAsString();

            if ($id <= 0) {
                return;
            }

            Communication::whereKey($id)->update([
                'status'  => CommunicationStatus::Sent->value,
                'sent_at' => now(),

                // The transport's own id, and the only hard evidence the
                // message left the building. Worth quoting to a host when
                // somebody insists nothing arrived.
                'message_id' => $event->sent->getMessageId(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not record mail delivery.', ['error' => $e->getMessage()]);
        }
    }
}
