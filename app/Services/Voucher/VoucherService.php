<?php

namespace App\Services\Voucher;

use App\Enums\Voucher\VoucherStatus;
use App\Enums\Voucher\VoucherType;
use App\Events\Voucher\VoucherIssued;
use App\Models\Auth\User;
use App\Models\Payment\Payment;
use App\Models\Reservation\Reservation;
use App\Models\Voucher\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use App\Services\Setting\SettingsRepository;

/**
 * PHASE 14A — issuing and redeeming, in one place.
 *
 * Redemption is the reason this class exists. It is the one operation in the
 * system where getting concurrency wrong means the same money is spent twice —
 * two staff members at a counter, or a visitor submitting a code on two tabs —
 * so it happens once, here, under a row lock, and nowhere else.
 */
class VoucherService
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Café credit
    |--------------------------------------------------------------------------
    */

    /**
     * Issue the café coupon a paid visit has earned, if it earned one.
     *
     * Returns null — not an exception — when there is nothing to issue. This is
     * called from inside payment settlement, and a workshop that simply does
     * not carry café credit is the ordinary case, not a failure. Throwing here
     * would roll back a payment because a coupon was not due.
     *
     * ONE COUPON PER RESERVATION, valued at the per-person figure times the
     * party size. Six people on a 50-taka visit get a single 300-taka coupon,
     * not six 50s — the client's decision, and the reason value is computed
     * here rather than stored per head.
     */
    public function issueCafeCredit(Reservation $reservation, ?Payment $payment = null): ?Voucher
    {
        $perPerson = $this->cafeCreditPerPerson($reservation);

        if ($perPerson <= 0) {
            return null;        // Not a credit-earning experience.
        }

        $value = round($perPerson * max(1, (int) $reservation->participants), 2);

        if ($value <= 0) {
            return null;
        }

        /*
         | Validity runs from the VISIT, not from now.
         |
         | Credit is issued the moment payment lands, which can be weeks before
         | the date. Counting thirty days from issue would hand somebody a
         | coupon that expired before they had even been to the studio.
         */
        $visitDate = CarbonImmutable::parse($reservation->reserved_date);
        $days      = (int) $this->settings->get('cafe_credit.validity_days', 30) ?: 30;

        try {
            return DB::transaction(function () use ($reservation, $value, $visitDate, $days, $payment) {
                $voucher = new Voucher();

                $voucher->forceFill([
                    'code'            => $this->generateCode(VoucherType::CafeCredit),
                    'type'            => VoucherType::CafeCredit,
                    'status'          => VoucherStatus::Active,
                    'value'           => $value,
                    'reservation_id'  => $reservation->id,
                    'valid_from'      => $visitDate->toDateString(),
                    'expires_at'      => $visitDate->addDays($days)->toDateString(),
                    'issued_to_name'  => $reservation->user?->name,
                    'issued_to_email' => $reservation->user?->email,
                    'note'            => 'Issued automatically on payment.',
                ])->save();

                VoucherIssued::dispatch($voucher);

                return $voucher;
            });
        } catch (QueryException $e) {
            /*
             | The unique (reservation_id, type) pair fired, which means credit
             | already exists for this visit. Expected rather than exceptional:
             | SSLCommerz sends both a browser redirect and an IPN, so a
             | settlement path can genuinely run twice.
             |
             | Swallowed and logged rather than rethrown, because the payment
             | that triggered this is real and must not be rolled back over a
             | duplicate coupon.
             */
            Log::info('Café credit already issued for this reservation.', [
                'reservation' => $reservation->reference_code,
                'payment'     => $payment?->reference,
            ]);

            return Voucher::where('reservation_id', $reservation->id)
                ->where('type', VoucherType::CafeCredit)
                ->first();
        }
    }

    /**
     * The per-head figure for whatever this reservation booked.
     *
     * Read from the WORKSHOP rather than from a global setting, so the studio
     * can change the amount, or add a second credit-earning visit type, without
     * a deploy. A reservation spanning several workshops takes the highest
     * figure among them — generous, and the alternative of summing them would
     * pay credit twice for one visit.
     */
    private function cafeCreditPerPerson(Reservation $reservation): float
    {
        return (float) $reservation->items
            ->map(fn ($item) => (float) ($item->workshop?->cafe_credit_per_person ?? 0))
            ->max();
    }

    /*
    |--------------------------------------------------------------------------
    | Gift vouchers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a gift voucher by hand.
     *
     * @param  array{value:float,expires_at:?string,workshop_id:?int,issued_to_name:?string,issued_to_email:?string,note:?string}  $data
     */
    public function issueGift(array $data, User $actor): Voucher
    {
        if (($data['value'] ?? 0) <= 0) {
            throw new RuntimeException('A voucher has to be worth something.');
        }

        return DB::transaction(function () use ($data, $actor) {
            $voucher = new Voucher();

            $voucher->forceFill([
                'code'            => $this->generateCode(VoucherType::Gift),
                'type'            => VoucherType::Gift,
                'status'          => VoucherStatus::Active,
                'value'           => round((float) $data['value'], 2),
                'workshop_id'     => $data['workshop_id'] ?? null,

                // Usable immediately. A gift voucher has no visit to wait for.
                'valid_from'      => null,
                'expires_at'      => $data['expires_at'] ?? null,

                'issued_to_name'  => $data['issued_to_name'] ?? null,
                'issued_to_email' => $data['issued_to_email'] ?? null,
                'note'            => $data['note'] ?? null,
                'issued_by'       => $actor->id,
            ])->save();

            if ($voucher->issued_to_email) {
                VoucherIssued::dispatch($voucher);
            }

            return $voucher;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Redemption
    |--------------------------------------------------------------------------
    */

    /**
     * Check a code without spending it.
     *
     * Separate from redeem() on purpose: the checkout needs to tell somebody
     * their code is good, and what it is worth, BEFORE anything is committed.
     * Doing that inside redeem() would mean a preview spent the voucher.
     *
     * @throws RuntimeException with a message meant to be read by the person
     *                          holding the code.
     */
    public function assertUsable(Voucher $voucher, ?Reservation $against = null): void
    {
        if ($reason = $voucher->unusableReason()) {
            throw new RuntimeException($reason);
        }

        if ($against === null) {
            return;
        }

        // The guard that keeps café credit out of the checkout. Asked of the
        // enum rather than compared here, so the rule has one home.
        if (! $voucher->type->paysForReservations()) {
            throw new RuntimeException(
                'Café credit is for food and drink at the studio. It cannot be used against a reservation.'
            );
        }

        if ($voucher->workshop_id !== null) {
            $booked = $against->items->pluck('workshop_id')->filter()->all();

            if (! in_array($voucher->workshop_id, $booked, true)) {
                throw new RuntimeException(
                    'This voucher is only valid for ' . ($voucher->workshop?->title ?? 'another experience') . '.'
                );
            }
        }
    }

    /**
     * Spend it. Single use, all or nothing.
     *
     * The lock and the re-read are the whole point. Two staff members scanning
     * the same coupon, or a visitor submitting a code from two tabs, both reach
     * this — and the second one finds a row that is already Redeemed and is
     * refused. Checking isRedeemable() before the lock would let both pass.
     *
     * No partial redemption: 180 spent against a 300 coupon forfeits the rest,
     * per the client's decision. A balance ledger is a different feature and
     * would need its own redemption history.
     *
     * @throws RuntimeException when it cannot be spent.
     */
    public function redeem(
        Voucher $voucher,
        User $actor,
        ?Reservation $against = null,
        ?string $note = null,
    ): Voucher {
        return DB::transaction(function () use ($voucher, $actor, $against, $note) {
            $voucher = Voucher::whereKey($voucher->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertUsable($voucher, $against);

            $voucher->forceFill([
                'status'                      => VoucherStatus::Redeemed,
                'redeemed_at'                 => CarbonImmutable::now(),
                'redeemed_by'                 => $actor->id,
                'redeemed_for_reservation_id' => $against?->id,
                'redemption_note'             => $note,
            ])->save();

            return $voucher;
        });
    }

    public function cancel(Voucher $voucher, User $actor, string $reason): Voucher
    {
        return DB::transaction(function () use ($voucher, $actor, $reason) {
            $voucher = Voucher::whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();

            if ($voucher->status === VoucherStatus::Redeemed) {
                throw new RuntimeException(
                    'This voucher has already been used and cannot be cancelled.'
                );
            }

            $voucher->forceFill([
                'status'              => VoucherStatus::Cancelled,
                'cancellation_reason' => $reason,
            ])->save();

            return $voucher;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * GIFT-2608-K4RT. Same alphabet as everything else that gets read aloud.
     */
    private function generateCode(VoucherType $type): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = $type->prefix() . '-' . now()->format('ym') . '-' . $suffix;
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}
