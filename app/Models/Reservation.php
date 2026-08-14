<?php

namespace App\Models;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Explicit, and deliberately narrow. Status, totals and every approval
     * column are absent: those are set by ReservationService, never by
     * anything coming off a public form.
     */
    protected $fillable = [
        'reference_code', 'user_id', 'reserved_date', 'start_time', 'end_time',
        'participants', 'special_requests', 'source', 'submitted_ip',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date'   => 'date',
            'participants'    => 'integer',
            'status'          => ReservationStatus::class,
            'source'          => ReservationSource::class,
            'subtotal'        => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount'    => 'decimal:2',
            'total_override'  => 'decimal:2',
            'approved_at'     => 'datetime',
            'declined_at'     => 'datetime',
            'confirmed_at'    => 'datetime',
            'cancelled_at'    => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference_code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    public function purposes(): BelongsToMany
    {
        return $this->belongsToMany(VisitPurpose::class, 'reservation_purpose');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class)->latest('created_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_column(ReservationStatus::open(), 'value'));
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Pending);
    }

    /** Everything waiting on somebody here — the real "to do" count. */
    public function scopeNeedingDecision(Builder $query): Builder
    {
        return $query->whereIn('status', array_column(ReservationStatus::needingDecision(), 'value'));
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('reserved_date', $date);
    }

    /**
     * Seats already committed on a date. Anything still open counts — a slot
     * held for someone awaiting payment is not free.
     *
     * Escalated is NOT here, for the same reason Pending is not: an undecided
     * request holds nothing. Adding it would let a queue of unanswered requests
     * close the studio to everyone else.
     */
    public function scopeHoldingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Approved->value,
            ReservationStatus::PaymentRequested->value,
            ReservationStatus::Confirmed->value,
        ]);
    }

    /**
     * The admin register's search box.
     *
     * Reference first and exactly, because that is what a visitor reads out
     * over the phone and a LIKE on it would put SHN-2608-A7K3 behind three
     * other rows. Everything else falls through to the visitor's own details:
     * staff search for the person far more often than for the booking.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('reference_code', 'like', $term . '%')
                ->orWhereHas('user', function (Builder $user) use ($term) {
                    $user->where('name', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $term . '%');
                });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return in_array($this->status, ReservationStatus::open(), true);
    }

    /**
     * Whether the visit itself may still be corrected.
     *
     * Up to and including Approved, the date, time and party size are just
     * details of a request and fixing a typo costs nothing. From
     * PaymentRequested onward there is a figure the visitor has been asked to
     * pay, and quietly re-pricing underneath that is how a studio ends up
     * taking the wrong amount of money. Phase 12 owns whatever the correct
     * answer is there — probably reissuing the payment request — so this stops
     * short of guessing.
     *
     * Notes stay editable at every status; they are not money.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            ReservationStatus::Pending,
            ReservationStatus::InfoRequested,
            ReservationStatus::Escalated,
            ReservationStatus::Approved,
        ], true);
    }

    public function isMoneyLocked(): bool
    {
        return ! $this->isEditable();
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    /** An Admin has agreed a figure that is not the price-list figure. */
    public function hasManualPrice(): bool
    {
        return $this->total_override !== null;
    }

    /** What the price list says, ignoring any agreed figure. */
    public function calculatedTotal(): float
    {
        return (float) $this->subtotal - (float) $this->discount_amount;
    }

    /** What will actually be charged. */
    public function payableTotal(): float
    {
        return $this->hasManualPrice()
            ? (float) $this->total_override
            : (float) $this->total_amount;
    }

    /**
     * How far the agreed figure sits from the calculated one. Negative is a
     * discount, positive a surcharge. Shown next to the price so nobody has to
     * work it out, and so a stale override after a party-size change is
     * obvious rather than merely present.
     */
    public function manualPriceDelta(): float
    {
        return $this->payableTotal() - $this->calculatedTotal();
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience
    |--------------------------------------------------------------------------
    */

    /** The session booked. Nullable only if a line item was never written. */
    public function workshop(): ?Workshop
    {
        return $this->items->first()?->workshop;
    }

    public function title(): string
    {
        return $this->items->first()?->title_snapshot ?? 'Visit';
    }
}
