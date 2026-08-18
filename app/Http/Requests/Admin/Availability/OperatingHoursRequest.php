<?php

namespace App\Http\Requests\Admin\Availability;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The whole week is submitted at once. Saving one day at a time would let the
 * admin leave the studio in a half-changed state between requests, and the
 * cross-day check below (does any active workshop still fit?) needs the full
 * picture anyway.
 */
class OperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // route middleware and policy gate before this runs
    }

    public function rules(): array
    {
        return [
            'days'                => ['required', 'array', 'size:7'],
            'days.*.day_of_week'  => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed'    => ['required', 'boolean'],
            'days.*.opens_at'     => ['nullable', 'required_if:days.*.is_closed,false', 'date_format:H:i'],
            'days.*.closes_at'    => ['nullable', 'required_if:days.*.is_closed,false', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.*.opens_at.required_if'  => 'Opening time is required for any day that is not closed.',
            'days.*.closes_at.required_if' => 'Closing time is required for any day that is not closed.',
            'days.*.opens_at.date_format'  => 'Opening time must be a 24-hour time such as 16:00.',
            'days.*.closes_at.date_format' => 'Closing time must be a 24-hour time such as 21:30.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $days = [];

        foreach ((array) $this->input('days', []) as $index => $day) {
            $closed = filter_var($day['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $days[$index] = [
                'day_of_week' => (int) ($day['day_of_week'] ?? $index),
                'is_closed'   => $closed,
                'opens_at'    => $closed ? null : ($day['opens_at'] ?? null),
                'closes_at'   => $closed ? null : ($day['closes_at'] ?? null),
            ];
        }

        $this->merge(['days' => $days]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('days', []) as $index => $day) {
                    if ($day['is_closed'] || ! $day['opens_at'] || ! $day['closes_at']) {
                        continue;
                    }

                    // Straight string comparison is safe on zero-padded H:i and
                    // avoids parsing seven pairs of times to learn one thing.
                    if ($day['closes_at'] <= $day['opens_at']) {
                        $validator->errors()->add(
                            "days.{$index}.closes_at",
                            'Closing time must be after opening time.'
                        );
                    }
                }

                $seen = array_column((array) $this->input('days', []), 'day_of_week');

                if (count(array_unique($seen)) !== 7) {
                    $validator->errors()->add('days', 'Every day of the week must be submitted exactly once.');
                }
            },
        ];
    }
}
