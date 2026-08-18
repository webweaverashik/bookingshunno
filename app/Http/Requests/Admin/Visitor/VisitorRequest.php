<?php

namespace App\Http\Requests\Admin\Visitor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contact details only. Nothing here can change a role, a password or the
 * derived reservation counters.
 */
class VisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // route middleware gates before this runs
    }

    public function rules(): array
    {
        $id = $this->route('visitor')?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],

            /*
             | Email is the identity key: ReservationService::resolveVisitor()
             | matches on it, so changing it here re-points every future
             | reservation from this person at a different row. Unique against
             | the whole users table, including staff and soft-deleted rows —
             | a soft-deleted account still owns its email until it is purged,
             | and reusing it would resurrect the wrong history.
             */
            'email' => [
                'required', 'email:rfc', 'max:190',
                Rule::unique('users', 'email')->ignore($id),
            ],

            // Same rule as the public form. Stored as a string, never numeric:
            // the Google Form export lost leading zeroes to Excel.
            'phone'    => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],

            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'    => 'Another account already uses that email address.',
            'phone.regex'     => 'Please enter a valid phone number.',
            'whatsapp.regex'  => 'Please enter a valid WhatsApp number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'      => trim((string) $this->input('name')),
            'email'     => strtolower(trim((string) $this->input('email'))),
            'phone'     => trim((string) $this->input('phone')) ?: null,
            'whatsapp'  => trim((string) $this->input('whatsapp')) ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
