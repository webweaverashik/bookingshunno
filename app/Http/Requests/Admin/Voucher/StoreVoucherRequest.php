<?php

namespace App\Http\Requests\Admin\Voucher;

use App\Services\Voucher\VoucherService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PHASE 14B — creating a gift voucher by hand.
 * PHASE 25  — the code is now typed rather than generated.
 *
 * Only gift vouchers reach this. Café credit is issued by the system on
 * settlement and has no form: letting staff mint it by hand would decouple it
 * from the visit that is supposed to earn it, and the unique
 * (reservation_id, type) constraint would refuse most attempts anyway.
 *
 * UpdateVoucherRequest extends this rather than repeating it, and overrides
 * exactly the two rules that differ. Two copies of the code pattern would
 * eventually disagree about what a code may look like, and the disagreement
 * would only surface as an edit that could not be saved.
 */
class StoreVoucherRequest extends FormRequest
{
    /**
     * A–Z, 0–9 and single hyphens between them. Never leading, trailing or
     * doubled — GIFT- and GIFT--EID are the kind of thing that gets typed once
     * and then read back wrongly for ever after.
     */
    public const CODE_PATTERN = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';

    public function authorize(): bool
    {
        return true;    // VoucherPolicy, in the controller.
    }

    /**
     * Normalised BEFORE validation, so the uniqueness rule below compares the
     * same string the database will eventually store. Without this, "eid gift"
     * would pass a unique check that "EIDGIFT" then fails at the insert.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => VoucherService::normaliseCode($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        return [
            /*
             | Capped at 24 by the column, and floored at 4 because a
             | three-character code is one somebody will hit by accident. The
             | uniqueness rule here is a courtesy that produces a good message;
             | the unique index is what actually prevents a duplicate, and
             | VoucherService catches the collision that lands between the two.
             */
            'code' => [
                'required', 'string', 'min:4', 'max:24',
                'regex:' . self::CODE_PATTERN,
                $this->codeUniqueRule(),
            ],

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

    /** Overridden by UpdateVoucherRequest to exclude the row being edited. */
    protected function codeUniqueRule(): ValidationRule
    {
        return Rule::unique('vouchers', 'code');
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Give this voucher a code.',
            'code.unique'   => 'That code is already in use. Every voucher code has to be unique.',
            'code.regex'    => 'Use letters and numbers only, with single hyphens between them.',
            'code.min'      => 'A code that short is too easy to hit by accident. Use at least four characters.',
            'code.max'      => 'Codes can be at most 24 characters.',

            'value.required'        => 'How much is this voucher worth?',
            'value.max'             => 'That is larger than any voucher the studio issues. Check the figure.',
            'expires_at.after'      => 'An expiry date has to be in the future.',
            'issued_to_email.email' => 'That does not look like an email address.',
        ];
    }
}
