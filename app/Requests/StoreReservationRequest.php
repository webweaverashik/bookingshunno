<?php

namespace App\Http\Requests;

use App\Support\ExperienceCatalogue;
use App\Support\SessionSlots;
use App\Support\VisitPurposes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // public form
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'min:2', 'max:120'],
            'email'        => ['required', 'string', 'email:rfc,dns', 'max:190'],
            // VARCHAR, never numeric: the Google Form export lost a leading
            // zero to Excel's float coercion (01406639867 -> 1.406639867E9).
            'phone'        => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'experience'   => ['required', 'string', 'in:' . implode(',', array_column(ExperienceCatalogue::all(), 'slug'))],
            'date'         => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'time'         => ['required', 'string', 'date_format:H:i'],
            'participants' => ['required', 'integer', 'min:1', 'max:30'],
            'purposes'     => ['nullable', 'array'],
            'purposes.*'   => ['string', 'in:' . implode(',', VisitPurposes::keys())],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'consent'      => ['accepted'],
            // Honeypot: a real person never fills this in.
            'website'      => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'     => 'Please enter a valid phone number.',
            'consent.accepted' => 'Please confirm before sending your request.',
            'website.prohibited' => 'Your request could not be sent. Please try again.',
        ];
    }

    /**
     * Rules that need more than one field to evaluate.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $date = $this->input('date');
                $time = $this->input('time');
                $slug = $this->input('experience');

                if ($date && ! $validator->errors()->has('date') && ! SessionSlots::isOpenOn($date)) {
                    $validator->errors()->add('date', 'We are closed on Sundays. Please choose another day.');
                }

                // The chosen start time must leave room for the session to
                // finish before 9:30 PM — never trust the front end for this.
                if ($slug && $time && ! $validator->errors()->hasAny(['experience', 'time'])) {
                    $experience = collect(ExperienceCatalogue::all())->firstWhere('slug', $slug);

                    if ($experience && ! array_key_exists($time, SessionSlots::forDuration((int) $experience['hours']))) {
                        $validator->errors()->add(
                            'time',
                            'That start time does not leave enough room for this session. Please pick another.'
                        );
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
        ]);
    }
}
