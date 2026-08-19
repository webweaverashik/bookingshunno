<?php

namespace App\Models\Reservation;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Reservation\ReservationSource;
use App\Enums\Reservation\ReservationStatus;
use App\Models\Auth\User;
use App\Models\Payment\Payment;
use App\Models\Workshop\Workshop;
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
            'reserved_date' => 'date',
            'participants' => 'integer',
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'total_override' => 'decimal:2',
            'approved_at' => 'datetime',
            'declined_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    /**
     * PHASE 12A. Newest first, because the register and the drawer both want
     * "the current request" far more often than the first one ever sent.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
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
            $inner->where('reference_code', 'like', $term.'%')
                ->orWhereHas('user', function (Builder $user) use ($term) {
                    $user->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%');
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
     * Open until the reservation closes. The studio does correct confirmed
     * bookings — somebody rings up, two of the six cannot come, the date moves
     * — and refusing to record that does not stop it happening; it just means
     * the register stops describing the visit that will actually take place.
     * Every correction is written into the status history, which is where a
     * re-priced booking becomes visible.
     *
     * The one hard stop is a closed reservation. Completed in particular: the
     * visit happened, and a record of what happened is not something anyone
     * gets to revise afterwards.
     *
     * The PRICE is a separate question — see isMoneyLocked() and
     * ReservationPolicy::setPrice().
     */
    public function isEditable(): bool
    {
        return ! $this->status->isClosed();
    }

    /**
     * Whether a figure has been quoted to the visitor or taken from them.
     *
     * Used to be the negation of isEditable(), which made "the visit may be
     * corrected" and "money is committed" one flag with two meanings. They are
     * different questions with different answers from PaymentRequested onward,
     * and this is the money half: it decides whether the agreed price may still
     * be set, and whether cancelling needs reservations.cancel-paid rather than
     * reservations.cancel.
     */
    public function isMoneyLocked(): bool
    {
        return in_array($this->status, [
            ReservationStatus::PaymentRequested,
            ReservationStatus::Confirmed,
            ReservationStatus::Completed,
        ], true);
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
    | Money — payments (Phase 12A)
    |--------------------------------------------------------------------------
    | These read the payments RELATION, so callers must have loaded it. That is
    | deliberate rather than lazy-loading per call: the drawer and the register
    | both eager-load, and a helper that quietly fired a query per row would
    | turn the reservation list into an N+1 the moment someone used it there.
    */

    /** The request currently awaiting payment, if there is one. */
    public function openPayment(): ?Payment
    {
        return $this->payments->first(fn (Payment $payment) => $payment->isOpen());
    }

    /** The most recent request of any status — what the drawer shows. */
    public function latestPayment(): ?Payment
    {
        return $this->payments->first();
    }

    /**
     * Everything actually received, across every request.
     *
     * Cancelled requests are excluded because cancel() refuses to run once
     * money has been taken, so a cancelled request has never received any. The
     * filter is here anyway: it costs nothing and it means this figure stays
     * correct if a later phase ever allows cancelling a refunded request.
     */
    public function amountPaid(): float
    {
        return (float) $this->payments
            ->reject(fn (Payment $payment) => $payment->status === PaymentStatus::Cancelled)
            ->sum(fn (Payment $payment) => (float) $payment->amount_paid);
    }

    /**
     * What the visitor still owes on the visit as a whole.
     *
     * The brief's "Remaining Amount". Reads the LIVE total rather than a
     * payment's snapshot, because this answers "what is owed now" — the
     * snapshot answers "what were they told", and the drawer shows both when
     * they disagree.
     */
    public function outstandingTotal(): float
    {
        return max(0, $this->payableTotal() - $this->amountPaid());
    }

    public function isFullyPaid(): bool
    {
        return $this->outstandingTotal() < 0.01 && $this->amountPaid() > 0;
    }

    /**
     * Nothing is owed on this visit.
     *
     * Not the same as isFullyPaid(), which requires money to have arrived. A
     * complimentary visit — a partner school, a comped session — is owed
     * nothing and has paid nothing, and must not be barred from completion for
     * failing to pay zero taka.
     *
     * Reads the payments relation, so callers must have loaded it.
     */
    public function hasNothingLeftToPay(): bool
    {
        return $this->payableTotal() <= 0.009 || $this->outstandingTotal() < 0.01;
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
