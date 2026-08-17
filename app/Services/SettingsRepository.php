<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settings the client can edit, with config/shunno.php as the fallback.
 *
 * Read through this rather than the model so the whole table is one cached
 * query per request instead of one per lookup.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'shunno.settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()
                /*
                 | PHASE 17 — secrets are excluded from this cache on purpose.
                 |
                 | Their stored value is ciphertext, so get('mail.password')
                 | would hand back an encrypted blob that looks like a password
                 | and works like nothing. Worse, that blob would then sit in the
                 | application cache, which is one more place it does not need to
                 | be. Secrets are read one at a time through getSecret().
                 */
                ->where('type', '!=', 'secret')
                ->get()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // config() is the fallback so a missing row degrades to the shipped
        // default rather than a null that quietly breaks pricing.
        return $this->all()[$key] ?? $default ?? config("shunno.{$key}");
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'type' => $type],
        );

        $this->flush();
    }

    /**
     * PHASE 17 — write several keys and flush the cache once.
     *
     * A settings form saves a dozen rows at a time. Calling set() in a loop
     * would flush the cache a dozen times, and every flush is followed by the
     * next lookup rebuilding the whole table — so a single save could rebuild
     * the cache twelve times over. In a transaction, so a form either saves
     * whole or not at all: half a payment configuration is worse than none.
     *
     * @param  array<string,array{value:mixed,type:string}>  $values
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $entry) {
                $value = $entry['value'];

                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => is_array($value) ? json_encode($value) : (string) $value,
                        'type'  => $entry['type'] ?? 'string',
                    ],
                );
            }
        });

        $this->flush();
    }

    /*
    |--------------------------------------------------------------------------
    | Secrets
    |--------------------------------------------------------------------------
    | PHASE 17 — the SMTP password, and anything else like it.
    |
    | Encrypted with APP_KEY before it touches the table, which makes the stored
    | value useless on its own. That matters because of where database rows go
    | and .env does not: nightly backups, a staging refresh, a dump on somebody's
    | laptop. A leaked backup should not hand over the studio's mail account.
    |
    | This is NOT the same standing as .env. The value is still readable by
    | anything that can run application code, and an Admin session can overwrite
    | it. It is the right trade for a mail password, which the client genuinely
    | needs to change without a deploy. It is NOT the right trade for the
    | payment gateway — see the note on the SSLCommerz tab for why those stay in
    | .env and stay read-only.
    |
    | Never returned to the browser. The form posts a new value or posts nothing.
    */
    public function setSecret(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => Crypt::encryptString($value), 'type' => 'secret'],
        );

        $this->flush();
    }

    public function getSecret(string $key): ?string
    {
        $stored = Setting::where('key', $key)->value('value');

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            /*
             | APP_KEY changed, or the row was written by hand. Treated as
             | "not set" rather than thrown: a mail password that cannot be
             | decrypted should fall back to .env and let mail keep working,
             | not take the whole panel down on the next page load.
             */
            Log::warning("Could not decrypt setting [{$key}]. Falling back to config.");

            return null;
        }
    }

    /** Whether a secret is stored at all, without decrypting or revealing it. */
    public function hasSecret(string $key): bool
    {
        return Setting::where('key', $key)->whereNotNull('value')->where('value', '!=', '')->exists();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
