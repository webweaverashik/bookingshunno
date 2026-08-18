<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 17 — the rules that decide what the public booking form will accept.
 *
 * Every bound here is a real one rather than a formality, because each of these
 * numbers can make the reservation form unusable if it is set carelessly and
 * nothing downstream will argue.
 */
class ReservationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Above 100 is not a studio evening, it is a typo.
            'max_participants'   => ['required', 'integer', 'min:1', 'max:100'],

            // Notice required before a visit. A fortnight is generous; beyond
            // that the calendar shows almost nothing bookable and looks broken.
            'min_lead_hours'     => ['required', 'integer', 'min:0', 'max:336'],

            // How far ahead the calendar opens. At least a week, or there is
            // barely anything to pick.
            'max_advance_days'   => ['required', 'integer', 'min:7', 'max:730'],

            /*
             | Start times every N minutes. Constrained to divisors of 60 so the
             | generated slots land on the hour — a 25-minute step produces
             | 4:00, 4:25, 4:50, 5:15, which is unreadable on a booking form and
             | impossible to staff.
             */
            'slot_step_minutes'  => ['required', 'integer', 'in:10,15,20,30,60'],

            'enforce_capacity'   => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slot_step_minutes.in' => 'Start times must divide the hour evenly: 10, 15, 20, 30 or 60 minutes.',
            'max_advance_days.min' => 'Opening the calendar less than a week ahead leaves visitors nothing to book.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enforce_capacity' => $this->boolean('enforce_capacity')]);
    }
}
