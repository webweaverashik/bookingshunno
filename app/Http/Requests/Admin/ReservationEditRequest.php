<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * PHASE 9 — correcting a reservation from the admin panel.
 *
 * The same AvailabilityService that governs the public form governs this one.
 * An admin correcting a date must not be able to put a booking somewhere the
 * public form would have refused — unless they deliberately override, which is
 * an Admin-only checkbox and is recorded.
 *
 * Notes are always accepted; the visit fields are only present in the payload
 * when the reservation is still editable, and are ignored otherwise.
 */
class ReservationEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller gates on the policy before this runs. A second,
        // divergent rule set here would be a place for the two to disagree.
        return true;
    }

    private function reservation(): Reservation
    {
        return $this->route('reservation');
    }

    public function rules(): array
    {
        $rules = [
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'note'             => ['nullable', 'string', 'max:500'],
        ];

        if (! $this->reservation()->isEditable()) {
            return $rules;
        }

        return $rules + [
            'reserved_date' => ['required', 'date_format:Y-m-d'],
            'start_time'    => ['required', 'date_format:H:i'],
            'participants'  => ['required', 'integer', 'min:1', 'max:200'],
            'override'      => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Keep the reason under 500 characters.',
        ];
    }

    /**
     * Availability, run exactly as the public form runs it.
     *
     * The override skips this and only this. It does not skip the group-size
     * ceiling or anything else — an admin who needs a session to take more
     * people than it seats should raise the workshop's own maximum, where the
     * change is visible to everyone rather than buried in one booking.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $reservation = $this->reservation();

                if (! $reservation->isEditable()) {
                    return;
                }

                if ($validator->errors()->hasAny(['reserved_date', 'start_time', 'participants'])) {
                    return;
                }

                $workshop = $reservation->workshop();

                if (! $workshop) {
                    $validator->errors()->add(
                        'reserved_date',
                        'This reservation has no workshop attached, so availability cannot be checked. Please raise it with the developer.'
                    );

                    return;
                }

                $limits = app(AvailabilityService::class)->participantLimits($workshop);

                if ((int) $this->input('participants') > $limits['max']) {
                    $validator->errors()->add(
                        'participants',
                        "{$workshop->title} seats {$limits['max']}. Raise the workshop's maximum first if this is a real change."
                    );

                    return;
                }

                if ($this->wantsOverride()) {
                    return;
                }

                $result = app(AvailabilityService::class)->check(
                    $workshop,
                    (string) $this->input('reserved_date'),
                    (string) $this->input('start_time'),
                    (int) $this->input('participants'),
                );

                if (! $result['ok']) {
                    $validator->errors()->add(
                        match ($result['field']) {
                            'time'         => 'start_time',
                            'participants' => 'participants',
                            default        => 'reserved_date',
                        },
                        $result['reason'] . ' Tick "save anyway" to book it regardless.'
                    );
                }
            },
        ];
    }

    /** Requested AND permitted. A Manager ticking it in the DOM gets nothing. */
    public function wantsOverride(): bool
    {
        return $this->boolean('override')
            && Gate::allows('overrideAvailability', $this->reservation());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'note' => trim((string) $this->input('note')) ?: null,
        ]);
    }
}
