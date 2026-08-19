<?php

namespace App\Events\Voucher;

use App\Models\Voucher\Voucher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A voucher exists and somebody should be told.
 *
 * Its own event rather than a branch inside payment settlement, because café
 * credit and gift vouchers are issued at completely different moments — one
 * automatically when money lands, the other by a member of staff at a desk —
 * and both need the same email.
 */
class VoucherIssued
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Voucher $voucher)
    {
    }
}
