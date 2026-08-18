<?php

namespace App\Enums\Communication;

/**
 * PHASE 13B — how far an email got.
 *
 * Three states, and the gap between the first two is the useful part. Queued
 * means we handed it to the queue; Sent means the mail transport accepted it
 * and gave us a message id. A row stuck at Queued is therefore a visible
 * symptom of a worker that is not running — which on shared hosting, driven by
 * a single cron entry, is a real and recurring failure mode worth being able to
 * see at a glance.
 *
 * None of these means "the visitor read it", and nothing here should ever be
 * labelled Delivered. SMTP acceptance is not delivery, and claiming otherwise
 * would have staff telling a visitor their email definitely arrived when it may
 * be sitting in a spam folder or have bounced an hour later.
 */
enum CommunicationStatus: string
{
    case Queued = 'queued';
    case Sent   = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Waiting to send',
            self::Sent   => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Sent   => 'success',
            self::Failed => 'danger',
        };
    }
}
