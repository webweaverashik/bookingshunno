<?php

namespace App\Http\Requests\Admin\Settings;

use App\Services\SettingsRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PHASE 19 — SSLCommerz credentials, now held in the database.
 *
 * This reverses the Phase 13 decision that credentials live in .env and only in
 * .env. That call was yours, and the reason for it has not gone away: database
 * rows travel in ways .env does not — nightly backups, a staging refresh, a
 * dump on a laptop. So the trade is bought back where it can be:
 *
 *   BOTH STORE PASSWORDS ARE ENCRYPTED WITH APP_KEY before they are written,
 *   through SettingsRepository::setSecret(). A leaked backup on its own is
 *   useless; an attacker needs the .env too. Same mechanism as the SMTP
 *   password.
 *
 *   NEITHER IS EVER SENT BACK to the browser. The fields render empty and an
 *   empty submission means unchanged, exactly as on the mail tab.
 *
 * ---------------------------------------------------------------------------
 * THE MODE SWITCH IS THE DANGEROUS PART
 * ---------------------------------------------------------------------------
 * Sandbox versus live used to follow the environment, precisely so that nobody
 * could put production into sandbox mode in two clicks. The symptom of that
 * mistake is payments that look successful to everyone involved and never
 * settle — found at month end, against a bank statement, long after the
 * workshops have been run.
 *
 * It is a setting now, so the guard has to live here instead: live mode is
 * refused unless live credentials actually exist. That stops the most likely
 * version of the accident — switching to live before the live store is set up —
 * but it cannot stop a deliberate switch the wrong way. The persistent banner
 * on the settings screen and the payments register is the rest of the answer.
 */
class GatewaySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:sandbox,live'],

            'sandbox_store_id'       => ['nullable', 'string', 'max:120'],
            'sandbox_store_password' => ['nullable', 'string', 'max:255'],

            'live_store_id'          => ['nullable', 'string', 'max:120'],
            'live_store_password'    => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * You may not select a mode whose credentials are missing.
     *
     * "Missing" means: not typed into this submission AND not already stored.
     * Checking only the submission would refuse a perfectly valid save where the
     * password was left blank to keep the existing one — which is the normal
     * way this form is used.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $mode     = (string) $this->input('mode');
                $settings = app(SettingsRepository::class);

                $storeId = filled($this->input("{$mode}_store_id"))
                    || filled($settings->get("sslcommerz.{$mode}_store_id"));

                $password = filled($this->input("{$mode}_store_password"))
                    || $settings->hasSecret("sslcommerz.{$mode}_store_password");

                if (! $storeId || ! $password) {
                    $validator->errors()->add(
                        'mode',
                        ucfirst($mode) . ' mode needs both a store ID and a store password. Fill them in first, then switch.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sandbox_store_id' => trim((string) $this->input('sandbox_store_id')) ?: null,
            'live_store_id'    => trim((string) $this->input('live_store_id')) ?: null,
        ]);
    }
}
