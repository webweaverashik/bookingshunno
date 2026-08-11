<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

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
            return Setting::all()
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

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
