<?php

namespace App\Enums\Voucher;

/**
 * PHASE 14A — where a voucher stands.
 *
 * Three cases. Expired is NOT one of them, for the same reason PaymentStatus
 * has no Expired: nothing would set it. Expiry is a date arithmetic against
 * expires_at, true the moment the day passes whether or not any code ran, and a
 * stored status would need a nightly job to stay honest — a job that, the first
 * time it failed to run, would leave the studio accepting coupons it believed
 * were dead.
 *
 * So a voucher stays Active and is simply no longer redeemable.
 * Voucher::isRedeemable() answers the real question, and the register shows
 * "Expired" as a derived label.
 *
 * Cancelled is separate and is a decision: somebody withdrew it.
 */
enum VoucherStatus: string
{
    case Active    = 'active';
    case Redeemed  = 'redeemed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active    => 'Active',
            self::Redeemed  => 'Redeemed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Active    => 'success',
            self::Redeemed  => 'primary',
            self::Cancelled => 'danger',
        };
    }
}
