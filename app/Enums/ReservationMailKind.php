<?php

namespace App\Enums;

/**
 * PHASE 11 — the notifications this system sends about a reservation.
 *
 * One enum rather than six near-identical Mailable classes. They differ only in
 * a subject line, a template and who receives them, and putting those three
 * things side by side means the whole set of outbound wording is readable in
 * one screen — which is exactly what the client will want to review, and what
 * would otherwise be scattered across six files.
 *
 * PHASE 12C added the two payment kinds. They arrive with a Payment attached —
 * see ReservationNotificationMail — because an amount, a deadline and a link
 * cannot be read off a reservation.
 *
 * There is no separate "final confirmation". The brief lists Payment
 * Confirmation and Final Reservation Confirmation as two emails, but they would
 * fire in the same second and say the same thing, and two messages about one
 * event teaches people to stop opening either. PaymentReceived does both jobs:
 * it is a receipt when money part-lands, and a receipt AND a confirmation when
 * it settles. A genuine pre-visit reminder — sent a day or two before the date,
 * with directions and what to bring — is worth having and is a different email
 * at a different time; it is not this one.
 */
enum ReservationMailKind: string
{
    case Received      = 'received';
    case InfoRequested = 'info_requested';
    case Approved      = 'approved';
    case Declined      = 'declined';
    case Cancelled     = 'cancelled';
    case Escalated     = 'escalated';

    // PHASE 12C.
    case PaymentRequested = 'payment_requested';
    case PaymentReceived  = 'payment_received';

    /**
     * Whether this goes to the visitor or to the studio.
     *
     * Escalated is the only internal one so far, and getting this wrong would
     * send a Manager's private note about a booking to the person it is about.
     * Hence a method rather than a convention about naming.
     */
    public function isInternal(): bool
    {
        return $this === self::Escalated;
    }

    /**
     * The reference is in every visitor-facing subject on purpose: it makes the
     * thread searchable for them and quotable to us, and it is what the admin
     * panel searches on.
     */
    public function subject(string $reference, string $studio): string
    {
        return match ($this) {
            self::Received      => "We have your request — {$reference}",
            self::InfoRequested => "A quick question about your visit — {$reference}",
            self::Approved      => "Your visit is approved — {$reference}",
            self::Declined      => "About your request — {$reference}",
            self::Cancelled     => "Your reservation has been cancelled — {$reference}",
            self::Escalated     => "[{$studio}] Decision needed — {$reference}",

            // Says what it wants, in the preview pane, without the word
            // "invoice". This is a small studio asking a person for money, not
            // a billing system.
            self::PaymentRequested => "Please complete your payment — {$reference}",
            self::PaymentReceived  => "Payment received — {$reference}",
        };
    }

    public function view(): string
    {
        return 'emails.reservations.' . str_replace('_', '-', $this->value);
    }

    /**
     * The status a transition lands on, mapped to what should go out.
     *
     * Returns null for statuses that send nothing, which is most of them:
     * Pending arrived at by a return-to-review is an internal tidy-up, and
     * Completed and NoShow are recorded after the visitor has already been and
     * gone. Silence is the right default — a system that emails on every state
     * change trains people to ignore it.
     */
    public static function forStatus(ReservationStatus $status): ?self
    {
        return match ($status) {
            ReservationStatus::InfoRequested => self::InfoRequested,
            ReservationStatus::Approved      => self::Approved,
            ReservationStatus::Declined      => self::Declined,
            ReservationStatus::Cancelled     => self::Cancelled,
            ReservationStatus::Escalated     => self::Escalated,

            /*
             | STILL NULL, and deliberately so.
             |
             | Both of these statuses DO now have an email, but it is raised
             | from App\Events\PaymentRequested and PaymentReceived, which carry
             | the Payment. If this match ever returns a kind for either, the
             | status listener fires as well and the visitor is emailed twice
             | about the same thing.
             */
            ReservationStatus::PaymentRequested,
            ReservationStatus::Confirmed     => null,

            default => null,
        };
    }
}
