<?php

namespace App\Enums\Voucher;

/**
 * PHASE 14A — where a voucher came from and what it buys.
 *
 * The distinction that matters is not "who paid for it" but WHERE IT IS SPENT,
 * because that decides whether it can ever touch a reservation total. Café
 * credit is spent at the counter on food and drink; letting it reduce a
 * workshop fee would turn a 300 taka thank-you into a 300 taka discount on the
 * thing that earned it.
 */
enum VoucherType: string
{
    case Gift       = 'gift';
    case CafeCredit = 'cafe_credit';

    public function label(): string
    {
        return match ($this) {
            self::Gift       => 'Gift voucher',
            self::CafeCredit => 'Café credit',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Gift       => 'GIFT',
            self::CafeCredit => 'CAFE',
        };
    }

    /** What this can be spent on, in the words the visitor is given. */
    public function spendableOn(): string
    {
        return match ($this) {
            self::Gift       => 'Any reservation at Studio Shunno.',
            self::CafeCredit => 'Food and drink at the café. Not against a reservation.',
        };
    }

    /**
     * Whether this may be used to settle a payment request.
     *
     * The single guard that keeps café credit out of the checkout. Asked by
     * VoucherService before any redemption against a reservation, so the rule
     * cannot be forgotten at one of several call sites.
     */
    public function paysForReservations(): bool
    {
        return $this === self::Gift;
    }

    /** Whether the studio issues it by hand, or the system does. */
    public function isAutomatic(): bool
    {
        return $this === self::CafeCredit;
    }
}
