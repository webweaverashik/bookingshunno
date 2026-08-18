<?php

namespace App\Http\Requests\Admin\Setting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PHASE 17 — SMTP.
 *
 * THE PASSWORD IS NULLABLE, AND THAT IS THE POINT. The stored value is never
 * sent to the browser, so the field renders empty on every load. If empty meant
 * "clear it", then opening this form to fix a typo in the from-name and hitting
 * Save would silently delete the password and stop every email the system
 * sends — approvals, payment links, receipts. Empty means unchanged; see
 * SettingController::updateMail().
 */
class MailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host'         => ['required', 'string', 'max:255'],
            'port'         => ['required', 'integer', 'min:1', 'max:65535'],
            'username'     => ['nullable', 'string', 'max:255'],

            /*
             | Laravel 11+ reads this as `scheme` and expects smtp or smtps.
             | The older 'tls' / 'ssl' spelling is offered in the form because
             | that is what every hosting control panel prints, and mapped in
             | the view — but only these four reach the config.
             */
            'encryption'   => ['nullable', 'in:smtp,smtps,tls,ssl'],

            'password'     => ['nullable', 'string', 'max:255'],

            // The address every notification is sent FROM. A wrong one here is
            // mail that silently lands in spam, so it is validated properly.
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['required', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'host.required'      => 'Without a host there is nothing to send through.',
            'from_address.email' => 'This is the address every reservation email comes from.',
        ];
    }
}
