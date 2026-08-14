<?php

namespace App\Http\Requests\Admin;

use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * One request class for all six decisions.
 *
 * They differ only in what the note is called and whether it is required, so
 * six near-identical classes would be six places to change one rule. The action
 * is read from the route rather than the payload: a client that could name its
 * own action could ask for the decline rules while hitting approve.
 *
 * What each note is FOR matters, and drives whether it is required:
 *   - decline and cancel: the visitor is told no, and Phase 11 puts this
 *     reason in the email. A blank rejection is not something to make easy.
 *   - request-info: the message IS the request. Without it the visitor gets an
 *     email asking for nothing.
 *   - escalate: the Admin picking this up did not speak to the visitor. What
 *     they are being asked to decide has to come from somewhere, and this is
 *     the only place it can.
 *   - approve and return-to-review: internal context, useful but not owed to
 *     anyone.
 */
class ReservationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The controller gates on the policy before this runs. A second,
        // divergent rule set here would be a place for the two to disagree.
        return true;
    }

    public function action(): string
    {
        return $this->route()->getActionMethod();
    }

    private function reservation(): Reservation
    {
        return $this->route('reservation');
    }

    public function rules(): array
    {
        return match ($this->action()) {
            'decline', 'cancel' => [
                'note'     => ['required', 'string', 'min:3', 'max:500'],
                'override' => ['nullable', 'boolean'],
            ],
            'requestInfo', 'escalate' => [
                'note'     => ['required', 'string', 'min:3', 'max:1000'],
                'override' => ['nullable', 'boolean'],
            ],
            default => [
                'note'     => ['nullable', 'string', 'max:500'],
                'override' => ['nullable', 'boolean'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'note.required' => match ($this->action()) {
                'decline'     => 'Please give a reason. The visitor will be told this.',
                'cancel'      => 'Please say why this is being cancelled.',
                'requestInfo' => 'Please write what you need from the visitor.',
                'escalate'    => 'Please say what the Admin needs to decide.',
                default       => 'Please add a note.',
            },
            'note.min' => 'That is too short to be useful to whoever reads it next.',
        ];
    }

    /**
     * Approving re-checks availability.
     *
     * A slot that was free when the request arrived on Monday may have filled
     * on Tuesday. Approving into it would hold seats the studio does not have,
     * and — because approved reservations count toward capacity — would then
     * push the next visitor out of a slot that was never really available.
     *
     * The override exists because the studio genuinely does open blocked hours
     * for people who ring up. It is Admin-only and it goes into the history.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->action() !== 'approve' || $this->wantsOverride()) {
                    return;
                }

                $reservation = $this->reservation();
                $workshop    = $reservation->workshop();

                if (! $workshop) {
                    $validator->errors()->add(
                        'note',
                        'This reservation has no workshop attached, so availability cannot be verified. Please raise it with the developer.'
                    );

                    return;
                }

                $result = app(AvailabilityService::class)->check(
                    $workshop,
                    $reservation->reserved_date->toDateString(),
                    substr((string) $reservation->start_time, 0, 5),
                    (int) $reservation->participants,
                );

                if (! $result['ok']) {
                    $validator->errors()->add(
                        'note',
                        $result['reason'] . ' Move the booking first, or approve it anyway if this is deliberate.'
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
