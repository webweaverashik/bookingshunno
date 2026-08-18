<?php

namespace App\Providers;

use App\Services\Setting\SettingsRepository;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * PHASE 17 — makes the SMTP settings form actually do something.
 *
 * THE TRAP THIS EXISTS TO AVOID. Writing mail.host into the settings table
 * changes nothing on its own. Laravel reads config/mail.php once at boot, from
 * .env, and the mailer is built from that — so without this provider an admin
 * could fill in the whole SMTP form, see "Saved", and go on sending through
 * whatever .env said, with no error anywhere to explain it. That failure is
 * silent and would be found weeks later by a visitor who never got an email.
 *
 * ---------------------------------------------------------------------------
 * .ENV STAYS THE FALLBACK
 * ---------------------------------------------------------------------------
 * Only keys with a real value in the database are applied. An empty field means
 * "not configured here", not "blank it out" — otherwise saving the form with
 * one field left empty would quietly break sending. So a fresh install runs
 * entirely on .env until somebody fills the form in, and a half-filled form
 * still leaves .env carrying the rest.
 *
 * ---------------------------------------------------------------------------
 * WHY EVERYTHING IS IN A TRY/CATCH
 * ---------------------------------------------------------------------------
 * This runs on every request, every queue job and every artisan command,
 * including `migrate` on a database where the settings table does not exist yet
 * and `db:seed` before it is populated. An exception here would make the
 * application unbootable and the fix unreachable — you could not migrate your
 * way out of it. Failing silently back to .env is the only safe behaviour.
 */
class RuntimeConfigServiceProvider extends ServiceProvider
{
    /**
     * Settings key => config key.
     *
     * The mailer name is hardcoded into the config paths because config/mail.php
     * ships one SMTP mailer and the settings form edits that one. If a second
     * ever appears, this map is what has to grow.
     */
    private const MAIL_MAP = [
        'mail.host' => 'mail.mailers.smtp.host',
        'mail.port' => 'mail.mailers.smtp.port',
        'mail.username' => 'mail.mailers.smtp.username',
        'mail.encryption' => 'mail.mailers.smtp.scheme',
        'mail.from_address' => 'mail.from.address',
        'mail.from_name' => 'mail.from.name',
    ];

    public function boot(): void
    {
        try {
            $settings = $this->app->make(SettingsRepository::class);

            $this->applyMailSettings($settings);
            $this->applyGatewaySettings($settings);
        } catch (Throwable) {
            // Table missing, cache unavailable, database asleep. .env carries on.
        }
    }

    private function applyMailSettings(SettingsRepository $settings): void
    {
        $values = $settings->all();

        foreach (self::MAIL_MAP as $settingKey => $configKey) {
            $value = $values[$settingKey] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            config([$configKey => $value]);
        }

        /*
         | The password is read one key at a time rather than from the cached
         | array, because SettingsRepository keeps secrets out of that cache
         | deliberately — see the note there. One extra query per boot, only
         | when a password has actually been stored.
         */
        if ($settings->hasSecret('mail.password')) {
            $password = $settings->getSecret('mail.password');

            if ($password !== null && $password !== '') {
                config(['mail.mailers.smtp.password' => $password]);
            }
        }

        /*
         | Switching the default mailer away from whatever .env says is
         | deliberate and narrow: only when a host has actually been configured
         | here. Local development runs MAIL_MAILER=log so that a queue that has
         | not drained cannot hide a broken email, and this must not undo that
         | on a machine where nobody has touched the settings form.
         */
        if (! empty($values['mail.host'])) {
            config(['mail.default' => 'smtp']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SSLCommerz
    |--------------------------------------------------------------------------
    | PHASE 19 — credentials moved out of .env and into the settings table, at
    | your instruction. Both stores are held: sandbox and live, with a mode
    | setting choosing between them.
    |
    | The whole point of resolving them INTO config here is that
    | SslCommerzService needs no change at all. It goes on reading
    | services.sslcommerz.store_id, .store_password and .sandbox exactly as it
    | did, including the rule that the API host follows the same switch as the
    | credentials — so the two can still never disagree, which was the reason
    | that rule existed.
    |
    | .env stays the fallback for an install where nothing has been saved yet.
    | An empty setting means "not configured here", never "blank it out".
    */
    private function applyGatewaySettings(SettingsRepository $settings): void
    {
        $values = $settings->all();
        $mode = $values['sslcommerz.mode'] ?? null;

        // Nothing configured in the database. Leave config/services.php and
        // whatever .env still holds entirely alone.
        if ($mode !== 'sandbox' && $mode !== 'live') {
            return;
        }

        $storeId = $values["sslcommerz.{$mode}_store_id"] ?? null;
        $password = $settings->getSecret("sslcommerz.{$mode}_store_password");

        /*
         | Both or neither.
         |
         | Applying a store ID without its password would produce a half-set of
         | credentials — the ID from the database, the password left over from
         | .env, belonging to a different store. SSLCommerz would reject the
         | session and the error would point at nothing useful. If either half
         | is missing, the whole thing is left as it was.
         */
        if (blank($storeId) || blank($password)) {
            return;
        }

        config([
            'services.sslcommerz.store_id' => $storeId,
            'services.sslcommerz.store_password' => $password,

            /*
             | The mode is applied even though it now comes from a row somebody
             | can edit. GatewaySettingsRequest refuses a mode whose credentials
             | are absent, and the settings screen carries a standing warning
             | when live is selected — those two are what replace the old
             | guarantee that this could only be changed by a deploy.
             */
            'services.sslcommerz.sandbox' => $mode === 'sandbox',
        ]);
    }
}
