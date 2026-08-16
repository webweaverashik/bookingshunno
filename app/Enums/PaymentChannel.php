<?php

namespace App\Enums;

/**
 * PHASE 12B — who asserted that this money arrived.
 *
 * Not the same question as PaymentMethod, which says HOW it arrived. The client
 * wants both routes open — online and manual — and at month end the difference
 * that matters is not bKash-versus-card but "a gateway verified this
 * server-side" versus "a member of staff wrote it down". The first is
 * reconcilable against a settlement report; the second is only as good as the
 * person who typed it.
 *
 * The payslip states this in plain words for the same reason. A receipt that
 * says "confirmed by SSLCommerz" and one that says "recorded at the studio by
 * Rifat" are different promises, and flattening both into "PAID" would hide
 * that from whoever has to reconcile them.
 */
enum PaymentChannel: string
{
    case Manual  = 'manual';
    case Gateway = 'gateway';

    // PHASE 14C. A third channel rather than folding vouchers into Manual,
    // because no money moved. A voucher settlement is the studio honouring a
    // promise it already made, and a payments report counting it as cash taken
    // would overstate the till by the value of every coupon redeemed.
    case Voucher = 'voucher';

    public function label(): string
    {
        return match ($this) {
            self::Manual  => 'Recorded at the studio',
            self::Gateway => 'Paid online',
            self::Voucher => 'Paid with a voucher',
        };
    }

    /** Wording for the payslip's verification line. */
    public function assurance(): string
    {
        return match ($this) {
            self::Manual  => 'Recorded by studio staff.',
            self::Gateway => 'Verified with the payment gateway.',
            self::Voucher => 'Settled by redeeming a voucher.',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Manual  => 'info',
            self::Gateway => 'success',
            self::Voucher => 'primary',
        };
    }

    /**
     * Derived from the method rather than passed alongside it.
     *
     * Two arguments that must agree is two arguments that eventually will not.
     * PaymentMethod already knows whether it is a hand-recorded method, so this
     * reads it rather than asking the caller to repeat itself.
     */
    public static function forMethod(PaymentMethod $method): self
    {
        return match ($method) {
            PaymentMethod::Sslcommerz => self::Gateway,
            PaymentMethod::Voucher    => self::Voucher,
            default                   => self::Manual,
        };
    }
}
