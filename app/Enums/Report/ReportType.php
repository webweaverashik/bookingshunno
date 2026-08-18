<?php

namespace App\Enums\Report;

/**
 * PHASE 16 — the four reports, and the one thing that separates them.
 *
 * Not just a list of names. Each report answers a question about a WINDOW OF
 * TIME, and the whole design of this phase turns on which date column defines
 * that window — because the same rows give four different totals depending on
 * which date you range on, and three of those answers are wrong.
 *
 *   RESERVATIONS ranges on the VISIT DATE. "How busy were we in August" is a
 *   question about when people came, not about when they filled in a form. A
 *   request submitted in July for an August visit belongs to August.
 *
 *   VISITORS ranges on the visit date too, and then aggregates. The report is
 *   "who came in this window, how often, and what they spent" — ranging on when
 *   the account was created would instead list people who signed up and never
 *   returned, which is a different and much less useful question.
 *
 *   PAYMENTS ranges on the TRANSACTION, not on the payment request. Money moves
 *   when it arrives, not when it was asked for. A booking fee requested on the
 *   30th and paid on the 2nd is next month's income, and an accountant reading
 *   a report that says otherwise will reconcile it against a bank statement and
 *   find a hole.
 *
 *   VOUCHERS ranges on ISSUE. A voucher is a liability from the moment it
 *   exists, and the report's job is to say how much of it the studio has taken
 *   on. Redemption is shown as a column, not as the range — ranging on
 *   redemption would hide every coupon still outstanding, which is exactly the
 *   figure that matters.
 *
 * The CSV headers live here too, beside the report they describe, so a column
 * cannot be added to the export and forgotten in the header row.
 */
enum ReportType: string
{
    case Reservations = 'reservations';
    case Visitors     = 'visitors';
    case Payments     = 'payments';
    case Vouchers     = 'vouchers';

    /*
    | PHASE 20 — the two LOGS.
    |
    | Reports of what the system did rather than of what the studio earned, and
    | they sit here rather than in a module of their own because they answer the
    | same shape of question: what happened between these dates. They inherit
    | the whole toolbar — range, filter, export — for free.
    |
    | They are also the only two that can be CLEARED, which is why
    | isClearable() exists below.
    */
    case Emails       = 'emails';
    case Gateway      = 'gateway';

    /*
    | PHASE 21 — who changed a setting.
    |
    | Narrow on purpose. An earlier draft logged eight models through a package;
    | six of those are already covered by ReservationStatusHistory,
    | LoginActivity, Communication and PaymentTransaction, or are low-stakes
    | reference data. This covers the one gap that opening the settings screen
    | to credentials actually created.
    |
    | Here rather than in a module of its own because it answers the same shape
    | of question as the other two logs — what happened between these dates —
    | and so inherits the range, the filter and all three exports for free.
    |
    | NOT clearable. See isClearable().
    */
    case Changes      = 'changes';

    public function label(): string
    {
        return match ($this) {
            self::Reservations => 'Reservations',
            self::Visitors     => 'Visitors',
            self::Payments     => 'Payments',
            self::Vouchers     => 'Vouchers',
            self::Emails       => 'Email log',
            self::Gateway      => 'Gateway log',
            self::Changes      => 'Settings changes',
        };
    }

    /** What the date range actually filters on, said plainly under the picker. */
    public function rangeBasis(): string
    {
        return match ($this) {
            self::Reservations => 'Ranged on the visit date.',
            self::Visitors     => 'Ranged on visit dates, then totalled per visitor.',
            self::Payments     => 'Ranged on when the money arrived, not when it was requested.',
            self::Vouchers     => 'Ranged on when the voucher was issued.',
            self::Emails       => 'Every message the system tried to send, ranged on when it was queued.',
            self::Gateway      => 'Every SSLCommerz attempt, successful or not, ranged on when it was made.',
            self::Changes      => 'Every change to a setting, and who made it.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Reservations => 'calendar-8',
            self::Visitors     => 'profile-user',
            self::Payments     => 'credit-cart',
            self::Vouchers     => 'gift',
            self::Emails       => 'sms',
            self::Gateway      => 'data',
            self::Changes      => 'shield-search',
        };
    }

    /**
     * The CSV header row.
     *
     * Written in the same order the exporter writes cells, and kept next to it
     * on purpose: a header list that lives in a different file from the row
     * builder drifts, and a shifted CSV column is the kind of error nobody
     * notices until the numbers have already been used.
     *
     * @return array<int,string>
     */
    public function csvHeaders(): array
    {
        return match ($this) {
            self::Reservations => [
                'Reference', 'Visit date', 'Start', 'End', 'Experience', 'Participants',
                'Status', 'Visitor', 'Email', 'Phone', 'Purposes',
                'Total (BDT)', 'Paid (BDT)', 'Outstanding (BDT)',
                'Source', 'Requested on', 'Approved on', 'Confirmed on',
            ],

            self::Visitors => [
                'Visitor', 'Email', 'Phone', 'WhatsApp',
                'Visits in range', 'Participants in range',
                'Billed in range (BDT)', 'Paid in range (BDT)',
                'Lifetime reservations', 'First seen', 'Last reservation',
            ],

            self::Payments => [
                'Receipt', 'Received on', 'Payment request', 'Reservation',
                'Visitor', 'Email', 'Channel', 'Method',
                'Amount (BDT)', 'Balance after (BDT)',
                'External reference', 'Recorded by',
            ],

            self::Vouchers => [
                'Code', 'Type', 'Status', 'Value (BDT)',
                'Issued on', 'Valid from', 'Expires',
                'Issued to', 'Email', 'Restricted to',
                'From reservation', 'Redeemed on', 'Redeemed against', 'Redeemed by',
            ],

            self::Emails => [
                'Queued at', 'Sent at', 'Status', 'Kind', 'To', 'Subject',
                'Reservation', 'Payment', 'Resend', 'Triggered by', 'Error',
            ],

            self::Gateway => [
                'Attempted at', 'Reference', 'Status', 'Reservation', 'Visitor',
                'Amount (BDT)', 'Method', 'External reference', 'Gateway message',
            ],

            self::Changes => [
                'When', 'Setting', 'Area', 'Changed from', 'Changed to', 'Changed by', 'IP address',
            ],
        };
    }

    /**
     * Whether the studio may delete rows from this report.
     *
     * Only the two logs, and even there the controller narrows it further —
     * see the note on ReportController::clear(). The other four are views over
     * reservations, payments and vouchers, and a "clear" button on those would
     * be a delete button on the business.
     */
    public function isClearable(): bool
    {
        /*
         | Settings changes are deliberately absent.
         |
         | A record of who changed the gateway mode, with a button that deletes
         | it, is not a record — and the person most likely to want it gone is,
         | by definition, the Admin whose change it describes.
         |
         | There is no pruning schedule either, unlike the other two logs. This
         | table grows by a handful of rows a month; keeping a decade of it costs
         | less than a single day of the email log.
         */
        return $this === self::Emails || $this === self::Gateway;
    }

    /** Filename stem for the download. The range is appended by the controller. */
    public function slug(): string
    {
        return $this->value;
    }

    /** @return array<int,self> */
    public static function all(): array
    {
        return self::cases();
    }
}
