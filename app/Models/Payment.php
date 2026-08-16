<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TransactionStatus;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    /**
     * Deliberately empty.
     *
     * Every column on this table is money, a deadline, or an audit field.
     * Nothing here should ever be settable from a request payload, even by an
     * admin form — PaymentService writes all of it explicitly. An empty
     * $fillable makes create([...]) fail loudly rather than a future
     * contributor's convenience shortcut succeeding quietly.
     */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type'              => PaymentType::class,
            'status'            => PaymentStatus::class,
            'method'            => PaymentMethod::class,
            'percentage'        => 'integer',
            'reservation_total' => 'decimal:2',
            'amount_due'        => 'decimal:2',
            'amount_paid'       => 'decimal:2',
            'gateway_payload'   => 'array',
            'due_at'            => 'datetime',
            'paid_at'           => 'datetime',
        ];
    }

    /**
     * The human reference, not the token.
     *
     * Admin URLs carry PAY-2608-K4RT so a reference quoted on the phone can be
     * pasted straight into the address bar. The public payment page binds on
     * the token instead and does so explicitly — see the route — because that
     * URL is the credential.
     */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * PHASE 12B — every receipt against this request, newest first.
     *
     * This is the authoritative record of what arrived and how. The method and
     * gateway_reference columns on this table are a denormalised copy of the
     * MOST RECENT one, kept so the register can answer "how was this paid"
     * without a join; never read them when a per-receipt answer is wanted.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest('received_at')->latest('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_column(PaymentStatus::open(), 'value'));
    }

    /**
     * Open, and the deadline has passed. The register's most useful filter and
     * the reason the (status, due_at) index exists.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->where('due_at', '<', now());
    }

    /**
     * Reference first and exactly — it is what a visitor reads out — then
     * through to the reservation and the person.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('reference', 'like', $term . '%')
                ->orWhere('gateway_reference', 'like', $term . '%')
                ->orWhereHas('reservation', function (Builder $reservation) use ($term) {
                    $reservation->where('reference_code', 'like', $term . '%')
                        ->orWhereHas('user', function (Builder $user) use ($term) {
                            $user->where('name', 'like', '%' . $term . '%')
                                ->orWhere('email', 'like', '%' . $term . '%')
                                ->orWhere('phone', 'like', '%' . $term . '%');
                        });
                });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    /**
     * PHASE 13 — the settled transactions, which are the only real receipts.
     *
     * Added because three separate templates independently filtered the
     * transactions list and one of them forgot, which is how a null
     * received_at reached a ->format() call on the visitor's own payment page.
     * A named method is harder to forget than a closure that has to be
     * remembered at every call site.
     *
     * Reads the loaded COLLECTION rather than querying, so callers keep the
     * eager load they already have and a list of payments does not become an
     * N+1.
     */
    public function receipts(): \Illuminate\Support\Collection
    {
        return $this->transactions->filter(
            fn (PaymentTransaction $transaction) => $transaction->status === TransactionStatus::Success
        );
    }

    /** What is still owed against THIS request. */
    public function outstanding(): float
    {
        return max(0, (float) $this->amount_due - (float) $this->amount_paid);
    }

    /** Something has arrived, but not all of it. */
    public function isPartiallyPaid(): bool
    {
        return $this->status === PaymentStatus::Pending && (float) $this->amount_paid > 0;
    }

    /**
     * What the whole reservation still owes after this request is settled.
     *
     * The brief's payment summary calls this "Remaining Amount" and it is the
     * figure a booking fee leaves behind. Read from the SNAPSHOT rather than
     * from the live reservation on purpose: this describes the arrangement the
     * visitor was told about, and it should not move because someone later
     * corrected the party size.
     */
    public function remainingOnReservation(): float
    {
        return max(0, (float) $this->reservation_total - (float) $this->amount_paid);
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Open, and the deadline has passed.
     *
     * The cast to bool is not decoration. `$this->due_at?->isPast()` yields
     * NULL when due_at is null, and `true && null` is null, not false — which
     * a `: bool` return type turns into a TypeError rather than a falsy value.
     * The column is NOT NULL today, so this cannot fire; it is one character of
     * insurance against a future nullable, and against a model hydrated from a
     * partial select that never loaded the column.
     */
    public function isOverdue(): bool
    {
        return (bool) ($this->isOpen() && $this->due_at?->isPast());
    }

    /**
     * Whether the snapshot still matches the reservation.
     *
     * An Admin who agrees a new price after sending a payment link creates
     * exactly this situation, and it is not an error — it is a thing somebody
     * needs to notice and act on. The drawer flags it; nothing auto-corrects,
     * because rewriting an amount a visitor has already been asked for is how a
     * studio takes the wrong money.
     */
    public function divergesFromReservation(): bool
    {
        $live = $this->reservation?->payableTotal();

        return $live !== null && abs($live - (float) $this->reservation_total) >= 0.01;
    }
}