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
 * The payment notifications are deliberately absent. PaymentRequested,
 * PaymentReceived and the final confirmation all carry an amount, a deadline
 * and a link that do not exist yet; Phase 12 adds them here alongside whatever
 * data they need.
 */
enum ReservationMailKind: string
{
    case Received      = 'received';
    case InfoRequested = 'info_requested';
    case Approved      = 'approved';
    case Declined      = 'declined';
    case Cancelled     = 'cancelled';
    case Escalated     = 'escalated';

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

            // PHASE 12/13 own these: they need an amount, a deadline and a link.
            ReservationStatus::PaymentRequested,
            ReservationStatus::Confirmed     => null,

            default => null,
        };
    }
}
