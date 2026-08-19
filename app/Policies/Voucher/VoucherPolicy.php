<?php

namespace App\Policies\Voucher;

use App\Enums\Voucher\VoucherStatus;
use App\Models\Auth\User;
use App\Models\Voucher\Voucher;

/**
 * PHASE 14B.
 *
 * Redeeming is granted to Manager as well as Admin, because that is the whole
 * point of café credit: somebody standing at the counter with a coupon needs
 * whoever is on the floor to be able to honour it. Making it Admin-only would
 * mean a visitor waiting while a manager phones the owner about 300 taka of
 * coffee.
 *
 * Creating and cancelling stay with Admin. Both are decisions that give away or
 * take back the studio's money, which is the line 10A and 10B drew and this
 * keeps.
 */
class VoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vouchers.view');
    }

    public function view(User $user, Voucher $voucher): bool
    {
        return $user->can('vouchers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vouchers.create');
    }

    /**
     * Whether the button is drawn. The harder questions — expired, not yet
     * valid, wrong experience — live on the model and in VoucherService, where
     * they can produce a MESSAGE. A policy that merely hid the button would
     * leave staff at a counter unable to explain why.
     */
    public function redeem(User $user, Voucher $voucher): bool
    {
        return $user->can('vouchers.redeem')
            && $voucher->status === VoucherStatus::Active;
    }

    public function cancel(User $user, Voucher $voucher): bool
    {
        return $user->can('vouchers.cancel')
            && $voucher->status === VoucherStatus::Active;
    }

    /*
    |--------------------------------------------------------------------------
    | Editing and deleting
    |--------------------------------------------------------------------------
    | These two DO hide their buttons on state, unlike redeem() above, and the
    | difference is who is standing there. Redemption is refused in front of a
    | visitor holding a coupon, so the button stays and the service supplies a
    | sentence to read out. Nobody is waiting on an edit — it is an admin alone
    | with a register — so a button that cannot work is just a button that lies.
    |
    | The model owns the rule. Asking it here rather than restating the
    | conditions means the policy and the service cannot drift apart.
    */

    public function update(User $user, Voucher $voucher): bool
    {
        return $user->can('vouchers.update') && $voucher->isEditable();
    }

    /**
     * Admin only, and separate from cancel() on purpose.
     *
     * Cancelling withdraws a voucher and says why. Deleting removes the fact
     * that it ever existed, which is the right answer only for a row created in
     * error — and the wrong answer for one that somebody out there is holding.
     */
    public function delete(User $user, Voucher $voucher): bool
    {
        return $user->can('vouchers.delete') && $voucher->isDeletable();
    }
}
