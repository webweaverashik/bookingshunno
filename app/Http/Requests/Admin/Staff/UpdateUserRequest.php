<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * PHASE 20 — editing a staff account.
 *
 * Identical to StoreUserRequest except that the password is nullable and the
 * uniqueness rule ignores this row.
 *
 * The rules that could lock the studio out of its own panel — demoting or
 * deactivating the last Admin, acting on yourself — are NOT here. They live in
 * UserController, because each needs to know which user is being edited AND who
 * is doing the editing, and because the same three checks guard the toggle and
 * delete endpoints too. Expressing them as validation rules would mean writing
 * them three times.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.update') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'  => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId)->withoutTrashed(),
            ],

            'phone'    => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],

            // Blank keeps the existing password.
            'password' => ['nullable', 'confirmed', Password::min(8)->uncompromised()],

            'role'      => ['required', Rule::in(['Admin', 'Manager'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'Another account already uses that address.',
            'password.confirmed' => 'The two passwords do not match.',
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
