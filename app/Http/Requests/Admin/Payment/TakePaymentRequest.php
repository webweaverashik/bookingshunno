<?php

namespace App\Http\Requests\Admin\Payment;

use App\Enums\Payment\PaymentMethod;
use App\Models\Reservation\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Money handed over at the counter, against a reservation rather than against
 * an existing request.
 *
 * Close to RecordPaymentRequest and deliberately separate. That one records
 * against a payment request the studio already sent, and the reservation is
 * implied by it; this one starts from the reservation and may have to raise the
 * request itself. Merging them would mean one class with two meanings for its
 * only required field.
 *
 * The AMOUNT is not bounded here. How much is outstanding depends on what other
 * payments exist, which has to be read under a row lock or two tills can each
 * take the last 500 taka — so PaymentService::collect() is where that is
 * decided, and this stops at "a positive number".
 */
class TakePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         | The same permission as recording against an open request, and
         | therefore available to Manager.
         |
         | Writing down money that arrived is a fact, not a decision — the same
         | reasoning that gave Manager the Record button and voucher redemption.
         | ASKING for money is the decision, and that stays on
         | payments.request with Admin. Nothing here emails a visitor to ask for
         | anything.
         */
        return $this->user()?->can('payments.update-status') ?? false;
    }

    public function rules(): array
    {
        return [
            'reservation_id' => [
                'required',
                Rule::exists((new Reservation())->getTable(), 'id'),
            ],

            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],

            // Only the methods a person can assert. SSLCommerz is written by
            // the gateway callback after server-side verification and Voucher
            // has to decrement a coupon; letting staff claim either by hand
            // would let the books record something that never happened.
            'method' => [
                'required',
                Rule::enum(PaymentMethod::class),
                Rule::in(array_column(PaymentMethod::manualOptions(), 'value')),
            ],

            'reference' => ['nullable', 'string', 'max:100'],

            // Backdating allowed, forward-dating not: money taken on Friday and
            // entered on Monday should read as Friday, but a receipt dated next
            // week is a typo at best.
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],

            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_id.required' => 'Choose which reservation this is for.',
            'reservation_id.exists'   => 'That reservation no longer exists.',
            'amount.gt'               => 'The amount received has to be more than zero.',
            'method.required'         => 'Say how the money arrived.',
            'method.in'               => 'That is not a payment method staff can record by hand.',
            'paid_at.before_or_equal' => 'A payment cannot be dated in the future.',
        ];
    }
}
