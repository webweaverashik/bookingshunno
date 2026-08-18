<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 17 — money rules.
 *
 * The tightest validation on this screen, because these are the only settings
 * that change what a visitor is charged. §25 of the brief puts anything
 * touching money in the category where a silent assumption is not acceptable.
 */
class PaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             | The booking-fee split. Minimum 1, not 0: a zero-percent booking
             | fee produces a payment request for nothing, which the payment
             | portal has no sensible way to present and the gateway would
             | reject. Someone who wants to stop taking deposits should be
             | choosing Full payment on the request, not setting this to zero.
             */
            'booking_fee_percentage'    => ['required', 'integer', 'min:1', 'max:100'],

            /*
             | Hours to pay after approval. At least one, because a deadline of
             | zero expires the request the moment it is sent. Capped at a
             | month — beyond that the date is holding a slot nobody has paid
             | for.
             */
            'payment_deadline_hours'    => ['required', 'integer', 'min:1', 'max:720'],

            'online_enabled'            => ['required', 'boolean'],

            // Group discount. min:2 because a "group" of one is just a price cut.
            'discount_min_participants' => ['required', 'integer', 'min:2', 'max:100'],

            // Capped at 50: a larger discount than half is a decision for the
            // owner and a conversation, not a number typed into a settings form.
            'discount_percentage'       => ['required', 'integer', 'min:0', 'max:50'],

            // Café credit is dated from the VISIT, not from issue. A year is
            // the outer bound before it stops being a nudge to come back.
            'cafe_credit_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'booking_fee_percentage.min' => 'A booking fee of zero would send a payment request for nothing. Use Full payment instead.',
            'discount_percentage.max'    => 'Discounts above 50% need the owner, not this form.',
            'payment_deadline_hours.min' => 'A deadline of zero hours expires the moment it is sent.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['online_enabled' => $this->boolean('online_enabled')]);
    }
}
