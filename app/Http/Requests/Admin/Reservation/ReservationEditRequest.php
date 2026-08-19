<?php

namespace App\Http\Requests\Admin\Reservation;

use App\Models\Reservation\Reservation;
use App\Services\Availability\AvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Correcting a reservation from the admin panel.
 *
 * The same AvailabilityService that governs the public form governs this one.
 * An admin correcting a date must not be able to put a booking somewhere the
 * public form would have refused — unless they deliberately override, which is
 * an Admin-only checkbox and is recorded.
 *
 * Notes are always accepted; the visit fields are only present in the payload
 * when the reservation is still editable, and are ignored otherwise.
 *
 * PHASE 10A adds the agreed price. Manager keeps the date, time and party size
 * — that is the point of the escalation flow, not an oversight — and only an
 * Admin holding reservations.discount-override may name a figure.
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
            'note' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->reservation()->isEditable()) {
            $rules += [
                'reserved_date' => ['required', 'date_format:Y-m-d'],
                'start_time' => ['required', 'date_format:H:i'],
                'participants' => ['required', 'integer', 'min:1', 'max:200'],
                'override' => ['nullable', 'boolean'],
            ];
        }

        if ($this->canSetPrice()) {
            $rules += [
                // Zero is allowed and meaningful: a comped visit for a partner
                // school is a real thing a studio does. Blank means "no agreed
                // price, use the price list".
                'total_override' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
                'total_override_reason' => ['nullable', 'string', 'max:255'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'note.max' => 'Keep the reason under 500 characters.',
            'total_override.numeric' => 'Enter the agreed total as a number, without the currency.',
            'total_override.min' => 'A total cannot be negative. Use 0 for a complimentary visit.',
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validatePrice($validator), fn (Validator $validator) => $this->validateAvailability($validator)];
    }

    /**
     * A figure with no explanation is unusable to whoever reads the record
     * next — and to whoever has to defend it to a visitor six weeks later.
     */
    private function validatePrice(Validator $validator): void
    {
        if (! $this->canSetPrice()) {
            return;
        }

        $amount = $this->input('total_override');

        if ($amount === null || $amount === '') {
            return;
        }

        if (trim((string) $this->input('total_override_reason')) === '') {
            $validator->errors()->add('total_override_reason', 'Say why this price was agreed. It goes into the reservation history.');
        }
    }

    /**
     * Availability, run exactly as the public form runs it.
     *
     * The override skips this and only this. It does not skip the group-size
     * ceiling or anything else — an admin who needs a session to take more
     * people than it seats should raise the workshop's own maximum, where the
     * change is visible to everyone rather than buried in one booking.
     */
    private function validateAvailability(Validator $validator): void
    {
        $reservation = $this->reservation();

        if (! $reservation->isEditable()) {
            return;
        }

        if ($validator->errors()->hasAny(['reserved_date', 'start_time', 'participants'])) {
            return;
        }

        $workshop = $reservation->workshop();

        if (! $workshop) {
            $validator->errors()->add('reserved_date', 'This reservation has no workshop attached, so availability cannot be checked. Please raise it with the developer.');

            return;
        }

        $limits = app(AvailabilityService::class)->participantLimits($workshop);

        if ((int) $this->input('participants') > $limits['max']) {
            $validator->errors()->add('participants', "{$workshop->title} seats {$limits['max']}. Raise the workshop's maximum first if this is a real change.");

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

            // Or a confirmed booking fails its own availability check: its four
            // people are already counted in the slot it is sitting in.
            except: $reservation,
        );

        if (! $result['ok']) {
            $validator->errors()->add(
                match ($result['field']) {
                    'time' => 'start_time',
                    'participants' => 'participants',
                    default => 'reserved_date',
                },
                $result['reason'].' Tick "save anyway" to book it regardless.',
            );
        }
    }

    /** Requested AND permitted. A Manager ticking it in the DOM gets nothing. */
    public function wantsOverride(): bool
    {
        return $this->boolean('override') && Gate::allows('overrideAvailability', $this->reservation());
    }

    public function canSetPrice(): bool
    {
        return Gate::allows('setPrice', $this->reservation());
    }

    /**
     * The agreed price as the service wants it, or absent entirely when this
     * person may not set one. Absent and null mean different things: null
     * removes an existing agreed price, absent leaves it untouched.
     *
     * @return array<string,mixed>
     */
    public function priceChanges(): array
    {
        if (! $this->canSetPrice()) {
            return [];
        }

        $amount = $this->input('total_override');
        $blank = $amount === null || $amount === '';

        return [
            'total_override' => $blank ? null : round((float) $amount, 2),
            'total_override_reason' => $blank ? null : trim((string) $this->input('total_override_reason')),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'note' => trim((string) $this->input('note')) ?: null,
        ]);
    }
}
