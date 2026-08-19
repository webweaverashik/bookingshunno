<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Studio identity and contact details.
 *
 * These are not decoration. contact.email is where staff notifications go and
 * what visitors are told to reply to; contact.phone and contact.whatsapp are
 * printed on the public site and in every email footer. A typo here is a
 * visitor who cannot reach the studio, so every one of them is required.
 */
class GeneralSettingsRequest extends FormRequest
{
    /** Route middleware has already checked permission:settings.update. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_name'           => ['required', 'string', 'max:120'],
            'contact_email'         => ['required', 'email', 'max:255'],
            'contact_phone'         => ['required', 'string', 'max:32'],
            'contact_whatsapp'      => ['nullable', 'string', 'max:32'],
            'contact_address'       => ['required', 'string', 'max:255'],
            'notifications_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_email.email'    => 'Staff notifications go to this address, so it has to be a real one.',
            'contact_phone.required' => 'This number is printed on the public site.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // An unchecked checkbox posts nothing at all, which fails a `boolean`
        // rule as "missing" rather than passing as false.
        $this->merge([
            'notifications_enabled' => $this->boolean('notifications_enabled'),
        ]);
    }
}
