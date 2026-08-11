<?php

namespace App\Services;

use App\Models\Workshop;

/**
 * The single place a reservation total is calculated.
 *
 * The popup shows a running total and the server recalculates it on submit;
 * both go through here so the two can never disagree. Amounts are handled in
 * integer poisha internally and rounded once at the boundary, which keeps
 * booking fee + remaining exactly equal to the total on odd amounts.
 */
class PricingService
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * @return array{subtotal: float, discount: float, total: float, discount_reason: ?string}
     */
    public function forWorkshop(Workshop $workshop, int $participants): array
    {
        return $this->calculate((float) $workshop->price, $participants);
    }

    public function calculate(float $unitPrice, int $participants): array
    {
        $subtotalPoisha = (int) round($unitPrice * 100) * max($participants, 1);

        $threshold = (int) $this->settings->get('group_discount.min_participants', config('shunno.group_discount.min_participants'));
        $percent   = (int) $this->settings->get('group_discount.percentage', config('shunno.group_discount.percentage'));

        $qualifies = $participants >= $threshold && $percent > 0;

        $discountPoisha = $qualifies
            ? (int) round($subtotalPoisha * $percent / 100)
            : 0;

        return [
            'subtotal'        => $subtotalPoisha / 100,
            'discount'        => $discountPoisha / 100,
            'total'           => ($subtotalPoisha - $discountPoisha) / 100,
            'discount_reason' => $qualifies ? "Group of {$participants} ({$percent}% off)" : null,
        ];
    }

    /**
     * PHASE 12: the booking-fee split. Kept here so the percentage is read from
     * settings in exactly one place when the payment module needs it.
     */
    public function split(float $total, bool $bookingFeeOnly): array
    {
        $percent = $bookingFeeOnly
            ? (int) $this->settings->get('booking_fee_percentage', config('shunno.booking_fee_percentage'))
            : 100;

        $totalPoisha   = (int) round($total * 100);
        $payablePoisha = (int) round($totalPoisha * $percent / 100);

        return [
            'percentage' => $percent,
            'payable'    => $payablePoisha / 100,
            'remaining'  => ($totalPoisha - $payablePoisha) / 100,
        ];
    }
}
