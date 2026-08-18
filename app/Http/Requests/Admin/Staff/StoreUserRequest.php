<?php

namespace App\Http\Requests\Admin\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * PHASE 20 — creating a staff account.
 *
 * The password is REQUIRED here and optional on update, which is the one
 * asymmetry worth explaining: a new account has no password to keep, so blank
 * would create an account nobody can sign into. On update, blank means
 * unchanged.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'min:2', 'max:120'],

            /*
             | withoutTrashed() because users are soft-deleted. Without it, a
             | removed colleague's address is blocked forever and the error says
             | only "already taken" — with no row visible anywhere to explain
             | why.
             */
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()],

            'phone'    => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]{6,32}$/'],

            /*
             | Same policy as a staff member changing their own: length plus a
             | check against known breached passwords, and no composition rules.
             | This one is typed BY an Admin FOR somebody else, which makes
             | uncompromised() more useful rather than less — a password chosen
             | on another person's behalf tends to be a memorable one.
             */
            'password' => ['required', 'confirmed', Password::min(8)->uncompromised()],

            // Only these two. Assigning Visitor here would create an account
            // that appears in staff management and can do nothing.
            'role'      => ['required', Rule::in(['Admin', 'Manager'])],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'An account already uses that address.',
            'password.confirmed' => 'The two passwords do not match.',
            'role.in'            => 'A staff account is either an Admin or a Manager.',
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
