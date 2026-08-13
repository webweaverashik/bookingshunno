<?php

namespace App\Http\Requests;

use App\Models\Workshop;
use App\Support\SessionSlots;
use App\Support\VisitPurposes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'name'  => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:190'],
            // VARCHAR, never numeric: the Google Form export lost a leading
            // zero to Excel's float coercion (01406639867 -> 1.406639867E9).
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],

            // PHASE 6: was an in: rule built from ExperienceCatalogue. An exists
            // rule scoped to active workshops means deactivating a session in
            // the admin panel closes it to new requests immediately, with no
            // deploy and no second list to keep in step.
            'experience' => [
                'required', 'string',
                Rule::exists('workshops', 'slug')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

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
            'experience.exists'  => 'That session is not currently available. Please choose another.',
            'phone.regex'        => 'Please enter a valid phone number.',
            'consent.accepted'   => 'Please confirm before sending your request.',
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
                    $workshop = $this->workshop();

                    if (
                        $workshop
                        && ! array_key_exists($time, SessionSlots::forMinutes($workshop->duration_minutes))
                    ) {
                        $validator->errors()->add(
                            'time',
                            'That start time does not leave enough room for this session. Please pick another.'
                        );
                    }
                }

                // NOT enforcing min_participants / max_participants here yet.
                // The seeded capacities are the placeholder 12 flagged in
                // WorkshopSeeder — rejecting a genuine 15-person group against
                // an unconfirmed number would lose the booking. Phase 7 turns
                // this on once the real capacities are entered in the admin
                // panel, together with seats already taken on the date.
            },
        ];
    }

    /**
     * Resolved once and memoised: three rules need it and the popup posts a
     * single slug.
     */
    public function workshop(): ?Workshop
    {
        static $workshop = false;

        if ($workshop === false) {
            $workshop = Workshop::query()
                ->active()
                ->where('slug', $this->input('experience'))
                ->first();
        }

        return $workshop;
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
