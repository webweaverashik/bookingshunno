<?php

namespace App\Services\Setting;

use App\Models\Setting\Setting;
use App\Models\Setting\SettingChange;
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
                 | Secrets are excluded from this cache on purpose.
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
        $written = is_array($value) ? json_encode($value) : (string) $value;

        $this->record($key, $this->rawValue($key), $written);

        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $written, 'type' => $type],
        );

        $this->flush();
    }

    /**
     * Write several keys and flush the cache once.
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
        /*
         | Read BEFORE the writes, in one query.
         |
         | Reading each old value inside the loop would work and would be one
         | query per key on a form that saves a dozen. It would also read a
         | value this same transaction had already changed if a caller ever
         | passed the same key twice.
         */
        $existing = Setting::whereIn('key', array_keys($values))
            ->pluck('value', 'key')
            ->all();

        DB::transaction(function () use ($values, $existing) {
            foreach ($values as $key => $entry) {
                $value   = $entry['value'];
                $written = is_array($value) ? json_encode($value) : (string) $value;

                $this->record($key, $existing[$key] ?? null, $written);

                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $written,
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
    | The SMTP password, and anything else like it.
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
        /*
         | Recorded as a secret, which means NEITHER VALUE IS WRITTEN.
         |
         | Not the old one and not the new one — not even the ciphertext. There
         | is no question this log needs a credential to answer: "the live store
         | password was changed by Rahman on Tuesday" is the whole useful
         | statement, and a second copy of the value in a table built for
         | reading on screen and exporting to CSV buys nothing.
         |
         | Logged unconditionally rather than only on change, because comparing
         | would mean decrypting the old value to see whether it differs — doing
         | the one thing this branch exists to avoid, in order to decide whether
         | to avoid it.
         */
        SettingChange::create([
            'key'        => $key,
            'is_secret'  => true,
            'changed_by' => auth()->id(),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

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

    /*
    |--------------------------------------------------------------------------
    | The change log
    |--------------------------------------------------------------------------
    | Here rather than in the controller because this class is the
    | single choke point every settings write already passes through — set(),
    | setMany() and setSecret() are the only three ways a row in that table
    | changes. Logging in the controller would mean five call sites today and a
    | sixth that somebody forgets tomorrow.
    */

    /**
     * Write a change row, if anything actually changed.
     *
     * Silent when the value is identical. A settings form posts every field on
     * every save, so without this an Admin correcting one typo would write
     * twelve rows saying nothing happened — and a log that is mostly noise is
     * one nobody reads, which is the same as not having it.
     */
    private function record(string $key, ?string $old, string $new): void
    {
        if ($old === $new) {
            return;
        }

        SettingChange::create([
            'key'        => $key,
            'old_value'  => $old,
            'new_value'  => $new,
            'is_secret'  => false,
            'changed_by' => auth()->id(),

            // request() is null under artisan, so a seeder or a console command
            // logs the change with no address rather than throwing.
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * The stored string, read past the cache and without casting.
     *
     * get() returns a typed value through the cached array — an integer, a
     * boolean — which cannot be compared against the string about to be
     * written. This reads the column itself.
     */
    private function rawValue(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }
}
