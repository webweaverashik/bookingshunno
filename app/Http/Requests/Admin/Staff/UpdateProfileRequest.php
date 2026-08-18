<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PHASE 17 — a staff member editing their own details.
 *
 * The unusual rule here is current_password, which is required ONLY when the
 * email address is being changed.
 *
 * Changing the address moves the account. It is the sign-in identifier, where
 * the login OTP is sent, and where a password reset would go — so an open
 * session that could change it unchallenged is a full account takeover from a
 * borrowed laptop, without ever knowing the password. Demanding it for a phone
 * number would be friction for nothing; demanding it for the address is the
 * difference between a session and the account itself.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'min:2', 'max:120'],

            /*
             | Unique across users, ignoring this one. withoutTrashed() because
             | the users table is soft-deleted: without it, a deleted account
             | blocks its own address forever and nobody can work out why.
             */
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id)->withoutTrashed(),
            ],

            'phone'            => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'whatsapp'         => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'current_password' => ['nullable', 'string'],
        ];
    }

    /**
     * The conditional check, run after the rules above pass.
     *
     * after() rather than a `required_if`, because the condition is not "a
     * field has a particular value" but "this field differs from what is in the
     * database" — which a rule string cannot express.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->user();

                if (strtolower((string) $this->input('email')) === strtolower($user->email)) {
                    return;
                }

                if (! Hash::check((string) $this->input('current_password'), $user->password)) {
                    $validator->errors()->add(
                        'current_password',
                        'Enter your current password to change the email address.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Another account already uses that address.',
            'phone.regex'  => 'That does not look like a phone number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'     => trim((string) $this->input('name')),
            'email'    => strtolower(trim((string) $this->input('email'))),
            'phone'    => trim((string) $this->input('phone')),
            'whatsapp' => trim((string) $this->input('whatsapp')) ?: null,
        ]);
    }
}
