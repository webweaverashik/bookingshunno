<?php

namespace App\Http\Requests\Admin\Availability;

use App\Models\Availability\BlockedDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BlockedDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function block(): ?BlockedDate
    {
        return $this->route('blockedDate');
    }

    public function rules(): array
    {
        return [
            // Past dates are allowed deliberately: the client may be recording
            // a closure after the fact so reports read correctly.
            'date'        => ['required', 'date_format:Y-m-d'],
            'is_full_day' => ['required', 'boolean'],
            'starts_at'   => ['nullable', 'required_if:is_full_day,false', 'date_format:H:i'],
            'ends_at'     => ['nullable', 'required_if:is_full_day,false', 'date_format:H:i'],
            'reason'      => ['nullable', 'string', 'max:190'],

            // Set by the front end after the admin confirms a clash. Never
            // trusted for anything except suppressing the second warning.
            'acknowledge' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.required_if' => 'A start time is required when blocking part of a day.',
            'ends_at.required_if'   => 'An end time is required when blocking part of a day.',
            'date.date_format'      => 'Please choose a valid date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $fullDay = $this->boolean('is_full_day');

        $this->merge([
            'is_full_day' => $fullDay,
            'starts_at'   => $fullDay ? null : $this->input('starts_at'),
            'ends_at'     => $fullDay ? null : $this->input('ends_at'),
            'acknowledge' => $this->boolean('acknowledge'),
            'reason'      => trim((string) $this->input('reason')) ?: null,
        ]);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $fullDay = $this->boolean('is_full_day');
                $start   = $this->input('starts_at');
                $end     = $this->input('ends_at');

                if (! $fullDay && $start && $end && $end <= $start) {
                    $validator->errors()->add('ends_at', 'The end time must be after the start time.');
                    return;
                }

                // A full-day block makes any other block on that date
                // meaningless, and two identical partial blocks are noise in a
                // list the admin has to scan.
                $duplicate = BlockedDate::query()
                    ->onDate((string) $this->input('date'))
                    ->when($this->block(), fn ($q) => $q->whereKeyNot($this->block()->id))
                    ->when(
                        $fullDay,
                        fn ($q) => $q,
                        fn ($q) => $q->where(fn ($inner) => $inner
                            ->where('is_full_day', true)
                            ->orWhere(fn ($overlap) => $overlap
                                ->where('starts_at', '<', $end)
                                ->where('ends_at', '>', $start))),
                    )
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add(
                        'date',
                        $fullDay
                            ? 'That date already has a block. Edit or remove the existing one instead.'
                            : 'That period overlaps a block already on this date.'
                    );
                }
            },
        ];
    }
}
