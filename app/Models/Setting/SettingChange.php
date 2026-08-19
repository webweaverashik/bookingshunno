<?php

namespace App\Models\Setting;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One settings change.
 *
 * Written by SettingsRepository and nothing else. There is no controller, no
 * form request and no update path: this is a record of something that happened,
 * and a row that can be edited from a form is not evidence of anything.
 *
 * No UPDATED_AT either. A row is written once and never touched again, so a
 * second timestamp column would only ever repeat the first.
 */
class SettingChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'key',
        'old_value',
        'new_value',
        'is_secret',
        'changed_by',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_secret'  => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /**
     * A readable name for the setting, without a lookup table to maintain.
     *
     * 'sslcommerz.live_store_id' becomes 'Sslcommerz — Live Store Id'. Not
     * perfect, and deliberately not fixed with a hand-written map: a map would
     * have to be kept in step with every key the settings screen adds, and a
     * missing entry would render as a blank rather than as an imperfect label.
     */
    public function label(): string
    {
        return collect(explode('.', $this->key))
            ->map(fn (string $part) => str($part)->replace('_', ' ')->headline()->toString())
            ->join(' — ');
    }

    /**
     * Which part of the settings screen this key lives on.
     *
     * Used for the filter dropdown and for colouring the row. Derived from the
     * key prefix, so a new key under an existing prefix needs nothing here.
     */
    public function group(): string
    {
        return match (true) {
            str_starts_with($this->key, 'sslcommerz.')     => 'Payment gateway',
            str_starts_with($this->key, 'mail.')           => 'Email',
            str_starts_with($this->key, 'availability.')   => 'Reservations',
            str_starts_with($this->key, 'contact.'),
            str_starts_with($this->key, 'studio.')         => 'Studio',
            str_starts_with($this->key, 'group_discount.'),
            str_starts_with($this->key, 'cafe_credit.'),
            str_starts_with($this->key, 'payments.'),
            in_array($this->key, ['booking_fee_percentage', 'payment_deadline_hours'], true) => 'Payments',
            default                                        => 'Other',
        };
    }

    /**
     * Whether this change is one somebody should look twice at.
     *
     * Three keys, and the test is the same for each: does getting it wrong fail
     * SILENTLY? A wrong studio phone number is visible on the site the moment it
     * is saved. A gateway switched to sandbox is not visible anywhere until a
     * bank statement disagrees a month later.
     */
    public function isSensitive(): bool
    {
        return $this->is_secret
            || $this->key === 'sslcommerz.mode'
            || $this->key === 'booking_fee_percentage'
            || str_starts_with($this->key, 'mail.');
    }

    /** "50 → 40", or "Set for the first time" when there was nothing before. */
    public function describe(): string
    {
        if ($this->is_secret) {
            // Said plainly. The value is not stored, so there is nothing to
            // withhold and nothing to imply was withheld.
            return 'Credential replaced — the value is not recorded here.';
        }

        $old = $this->display($this->old_value);
        $new = $this->display($this->new_value);

        return $this->old_value === null || $this->old_value === ''
            ? "Set to {$new}"
            : "{$old} → {$new}";
    }

    private function display(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        // Booleans are stored as '1'/'0' by the settings table. Rendering those
        // as-is makes "notifications.enabled: 1 → 0" read like a number change.
        return match ($value) {
            '1'     => 'on',
            '0'     => 'off',
            default => $value,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForKey(Builder $query, ?string $key): Builder
    {
        return $key ? $query->where('key', $key) : $query;
    }
}
