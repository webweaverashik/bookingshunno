<?php

namespace App\Http\Requests\Public;

use App\Models\Workshop\Workshop;
use App\Services\Availability\AvailabilityService;
use App\Services\Setting\SettingsRepository;
use App\Support\Reservation\VisitPurposes;
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
        $ceiling = (int) app(SettingsRepository::class)->get('reservation.max_participants', 30);

        return [
            'name'  => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:190'],
            // VARCHAR, never numeric: the Google Form export lost a leading
            // zero to Excel's float coercion (01406639867 -> 1.406639867E9).
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],

            // An exists rule scoped to active workshops means deactivating a
            // session in the admin panel closes it to new requests at once.
            'experience' => [
                'required', 'string',
                Rule::exists('workshops', 'slug')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],

            'date'         => ['required', 'date_format:Y-m-d'],
            'time'         => ['required', 'string', 'date_format:H:i'],
            'participants' => ['required', 'integer', 'min:1', 'max:' . $ceiling],
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
     * Every date, slot and capacity rule now comes from
     * AvailabilityService. This class previously owned a Sunday check and a
     * slot-fits check of its own, which meant the same business rule existed in
     * two places and only one of them read the operating_hours table.
     *
     * This runs on every submission, including one crafted by hand against the
     * endpoint directly. The popup's own slot list is a convenience, not a
     * gate (§19).
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['experience', 'date', 'time', 'participants'])) {
                    return;
                }

                $workshop = $this->workshop();

                if (! $workshop) {
                    return;   // the exists rule has already failed
                }

                $result = app(AvailabilityService::class)->check(
                    $workshop,
                    (string) $this->input('date'),
                    (string) $this->input('time'),
                    (int) $this->input('participants'),
                );

                if (! $result['ok']) {
                    $validator->errors()->add($result['field'] ?? 'date', $result['reason']);
                }
            },
        ];
    }

    /**
     * Resolved once and memoised: the availability check and the controller
     * both need it, and the popup posts a single slug.
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
