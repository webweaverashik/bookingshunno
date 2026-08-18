<?php

namespace App\Http\Requests\Admin\Voucher;

use App\Models\Voucher\Voucher;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * PHASE 25 — editing a gift voucher.
 *
 * Everything StoreVoucherRequest says, with two rules replaced:
 *
 *   code        unique among OTHER vouchers. Without the ignore, saving a form
 *               without touching the code would fail against the voucher's own
 *               row — the classic edit-form bug.
 *
 *   expires_at  may stay in the past if it was already in the past. An expired
 *               gift voucher is still editable (correcting a recipient's name
 *               on one is perfectly ordinary), and a blanket "must be in the
 *               future" would force whoever did that to silently revive it.
 */
class UpdateVoucherRequest extends StoreVoucherRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'expires_at' => ['nullable', 'date', $this->expiryRule()],
        ]);
    }

    protected function codeUniqueRule(): ValidationRule
    {
        return Rule::unique('vouchers', 'code')->ignore($this->voucher()->getKey());
    }

    /**
     * Future, unless it is the date already on the record.
     *
     * Compared as Y-m-d strings rather than as dates, because that is the
     * format the field submits and a Carbon comparison here would differ from
     * the one the browser's date picker just made.
     */
    protected function expiryRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($value === $this->voucher()->expires_at?->format('Y-m-d')) {
                return;     // Unchanged. Leave it alone.
            }

            if (CarbonImmutable::parse((string) $value)->endOfDay()->isPast()) {
                $fail('A new expiry date has to be in the future.');
            }
        };
    }

    protected function voucher(): Voucher
    {
        return $this->route('voucher');
    }
}
