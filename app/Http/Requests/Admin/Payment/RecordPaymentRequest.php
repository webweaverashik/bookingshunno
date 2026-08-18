<?php

namespace App\Http\Requests\Admin\Payment;

use App\Enums\Payment\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PHASE 12A — writing down money that arrived by hand.
 *
 * This one DOES take an amount, unlike StorePaymentRequest, and the difference
 * is the point: there the server knows what should be charged, here only the
 * person holding the cash knows what turned up. The ceiling — you cannot record
 * more than is outstanding — is enforced in PaymentService under a lock rather
 * than here, because it depends on a figure that can move between this
 * validation and the write.
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;    // PaymentPolicy::record(), in the controller.
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],

            /*
             | Only the manual methods. Sslcommerz is excluded from the list so
             | no one can assert a gateway settlement by hand — Phase 13's
             | callback writes that, having verified it server-side. Voucher is
             | excluded because redeeming one has to decrement it, which is
             | Phase 14's job.
             */
            'method' => ['required', Rule::in(array_column(PaymentMethod::manualOptions(), 'value'))],

            // bKash transaction ID, a cheque number, whatever was written on
            // the slip. Free text because it is somebody else's format.
            'reference' => ['nullable', 'string', 'max:100'],

            // Defaults to now. Backdating is allowed — money taken on Friday
            // and entered on Monday should read as Friday — but not
            // forward-dating, which would put a receipt in the future.
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],

            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'      => 'How much was received?',
            'amount.min'           => 'The amount has to be more than zero.',
            'method.required'      => 'How did the money arrive?',
            'method.in'            => 'Pick one of the listed methods.',
            'paid_at.before_or_equal' => 'A payment cannot be recorded as arriving in the future.',
        ];
    }

    public function method(): PaymentMethod
    {
        return PaymentMethod::from($this->validated()['method']);
    }

    public function paidAt(): ?CarbonImmutable
    {
        $value = $this->validated()['paid_at'] ?? null;

        return $value ? CarbonImmutable::parse($value) : null;
    }
}
