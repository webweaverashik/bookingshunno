<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PHASE 14B — creating a gift voucher by hand.
 *
 * Only gift vouchers reach this. Café credit is issued by the system on
 * settlement and has no form: letting staff mint it by hand would decouple it
 * from the visit that is supposed to earn it, and the unique
 * (reservation_id, type) constraint would refuse most attempts anyway.
 */
class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;    // VoucherPolicy::create(), in the controller.
    }

    public function rules(): array
    {
        return [
            /*
             | Capped at 50,000. Not because a larger voucher is impossible but
             | because a mistyped extra zero on a gift is money the studio has
             | promised somebody, and it is far cheaper to refuse it here than
             | to explain later why a 500,000 taka voucher is not being honoured.
             */
            'value' => ['required', 'numeric', 'min:1', 'max:50000'],

            // Optional. A voucher with no expiry is a liability that sits on the
            // books for ever, but the client has not asked for a mandatory one
            // and inventing a default would quietly kill gifts they meant to be
            // open-ended.
            'expires_at' => ['nullable', 'date', 'after:today'],

            // Restricts the voucher to one experience. Null means any.
            'workshop_id' => ['nullable', 'integer', Rule::exists('workshops', 'id')],

            'issued_to_name'  => ['nullable', 'string', 'max:255'],

            // If given, the voucher is emailed on creation. If not, the code is
            // shown once in the panel for staff to write on a card.
            'issued_to_email' => ['nullable', 'email', 'max:255'],

            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required'    => 'How much is this voucher worth?',
            'value.max'         => 'That is larger than any voucher the studio issues. Check the figure.',
            'expires_at.after'  => 'An expiry date has to be in the future.',
            'issued_to_email.email' => 'That does not look like an email address.',
        ];
    }
}
