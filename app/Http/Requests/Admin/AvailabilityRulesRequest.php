<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The three settings AvailabilityService reads on every check.
 *
 * enforce_capacity is the one that matters: it is the switch that turns the
 * whole capacity pathway on once the client confirms real per-session numbers.
 */
class AvailabilityRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enforce_capacity' => ['required', 'boolean'],

            // Zero is legitimate — same-day walk-in requests. The ceiling stops
            // a typo from closing bookings for a fortnight.
            'min_lead_hours'   => ['required', 'integer', 'min:0', 'max:336'],

            // At least a week ahead, or the studio cannot take a booking for
            // next weekend. Two years is the practical ceiling.
            'max_advance_days' => ['required', 'integer', 'min:7', 'max:730'],
        ];
    }

    public function messages(): array
    {
        return [
            'min_lead_hours.max'   => 'Minimum notice cannot exceed two weeks.',
            'max_advance_days.min' => 'Visitors must be able to book at least a week ahead.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enforce_capacity' => $this->boolean('enforce_capacity'),
        ]);
    }
}
