<?php

namespace App\Http\Requests\Visitor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The only thing a visitor may write.
 *
 * Three fields, and email is not among them: see the note on
 * VisitorAreaController::updateAccount() for why the address is not editable
 * from a session.
 *
 * The phone pattern is the same one StoreReservationRequest uses, kept
 * deliberately loose. Bangladeshi mobile numbers are typed with and without
 * the country code and with spaces and dashes in them, and a form that refuses
 * "+880 1712 345678" teaches people to lie to it.
 */
class UpdateVisitorProfileRequest extends FormRequest
{
    /** EnsureVisitor has already established who this is. */
    public function authorize(): bool
    {
        return $this->user() !== null && ! $this->user()->isStaff();
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:120'],
            'phone'    => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Please tell us what to call you.',
            'phone.required' => 'We need a number in case we have to reach you about a booking.',
            'phone.regex'    => 'That does not look like a phone number.',
            'whatsapp.regex' => 'That does not look like a phone number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'     => trim((string) $this->input('name')),
            'phone'    => trim((string) $this->input('phone')),
            'whatsapp' => trim((string) $this->input('whatsapp')) ?: null,
        ]);
    }
}
