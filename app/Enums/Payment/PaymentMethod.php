<?php

namespace App\Enums\Payment;

use App\Models\Voucher\Voucher;

/**
 * How money actually arrived.
 *
 * An enum rather than a free-text field because this is what the payment report
 * groups by in Phase 16, and "bKash", "Bkash", "bkash " and "BKASH" are four
 * rows in that report if staff type it themselves.
 *
 * The split between Bkash/Nagad/Card and Sslcommerz is intentional and is the
 * distinction that matters to the studio's bookkeeping: the first three mean
 * somebody sent money to a personal or merchant number and a human wrote it
 * down, the last means the gateway settled it and there is a transaction ID to
 * reconcile against. Phase 13 only ever writes Sslcommerz; everything else is
 * recorded by hand.
 *
 * Voucher is here rather than in Phase 14 because the column needs to be able
 * to express it the moment vouchers exist, and adding a case later would mean a
 * migration on the report's grouping. Nothing writes it yet.
 */
enum PaymentMethod: string
{
    case Cash         = 'cash';
    case Bkash        = 'bkash';
    case Nagad        = 'nagad';
    case Card         = 'card';
    case BankTransfer = 'bank_transfer';
    case Sslcommerz   = 'sslcommerz';
    case Voucher      = 'voucher';
    case Other        = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash         => 'Cash',
            self::Bkash        => 'bKash',
            self::Nagad        => 'Nagad',
            self::Card         => 'Card',
            self::BankTransfer => 'Bank transfer',
            self::Sslcommerz   => 'Online (SSLCommerz)',
            self::Voucher      => 'Gift voucher',
            self::Other        => 'Something else',
        };
    }

    /**
     * Whether a human recorded this rather than a gateway confirming it.
     *
     * The drawer says so next to the figure. "Marked paid by Rifat" and
     * "settled by SSLCommerz, txn 24081512345" carry very different weight when
     * somebody is reconciling at the end of the month, and a single "Paid" for
     * both would hide that.
     */
    public function isManual(): bool
    {
        return $this !== self::Sslcommerz;
    }

    /**
     * The methods an admin may choose when recording by hand.
     *
     * Sslcommerz is excluded: it is written by the gateway callback in Phase 13
     * and offering it here would let staff assert a settlement that never
     * happened. Voucher is excluded for the same reason — Phase 14 redeems, and
     * redemption has to decrement the voucher.
     *
     * @return array<int,self>
     */
    public static function manualOptions(): array
    {
        return [self::Cash, self::Bkash, self::Nagad, self::Card, self::BankTransfer, self::Other];
    }
}
