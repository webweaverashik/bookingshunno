<?php

namespace App\Http\Requests\Admin\Payment;

use App\Enums\Payment\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The "ask the visitor for money" form.
 *
 * Notably absent: an amount. The figure is derived from the reservation and the
 * type, by PricingService, on the server. A payload that could name its own
 * amount would let anyone who can reach this endpoint charge whatever they
 * liked, and would also let a stale modal send yesterday's total.
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller gates on ReservationPolicy::requestPayment() before
        // this runs. A second rule set here is a second thing to keep in step.
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(PaymentType::class)],

            /*
             | Optional override of the configured deadline, in hours.
             |
             | Capped at 720 — thirty days. Not because a longer deadline is
             | impossible but because a payment request that sits open for
             | months holds a slot against the capacity check, and a mistyped
             | 4800 should be caught here rather than discovered in November.
             */
            'deadline_hours' => ['nullable', 'integer', 'min:1', 'max:720'],

            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'        => 'Choose whether this is the booking fee or the full amount.',
            'deadline_hours.max'   => 'That is more than thirty days away. Pick a shorter deadline.',
            'deadline_hours.min'   => 'The deadline has to be at least an hour from now.',
        ];
    }

    public function paymentType(): PaymentType
    {
        return PaymentType::from($this->validated()['type']);
    }

    public function deadlineHours(): ?int
    {
        $hours = $this->validated()['deadline_hours'] ?? null;

        return $hours === null ? null : (int) $hours;
    }
}
