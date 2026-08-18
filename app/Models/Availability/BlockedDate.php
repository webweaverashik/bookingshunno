<?php

namespace App\Models\Availability;

use App\Models\Concerns\HasCreatedBy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A day, or part of a day, the studio cannot take bookings for: holidays,
 * private hires, installation days.
 *
 * PHASE 7B: swapped its hand-written creator() for HasCreatedBy, which also
 * fills created_by automatically — the admin should never have to send it, and
 * a client-supplied created_by would be worth nothing anyway.
 */
class BlockedDate extends Model
{
    use HasCreatedBy;

    protected $fillable = ['date', 'is_full_day', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'is_full_day' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /** Today onward. Past blocks are history and clutter the admin list. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', CarbonImmutable::today()->toDateString());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('date', '<', CarbonImmutable::today()->toDateString());
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function windowLabel(): string
    {
        if ($this->is_full_day) {
            return 'All day';
        }

        return CarbonImmutable::createFromTimeString($this->starts_at)->format('g:i A')
            . ' – '
            . CarbonImmutable::createFromTimeString($this->ends_at)->format('g:i A');
    }

    public function isPast(): bool
    {
        return $this->date->lessThan(CarbonImmutable::today());
    }
}
