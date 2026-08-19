<?php

namespace App\Models\Voucher;

use App\Enums\Voucher\VoucherStatus;
use App\Enums\Voucher\VoucherType;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Reservation\Reservation;
use App\Models\Workshop\Workshop;

class Voucher extends Model
{
    use HasFactory;

    /** Written only by VoucherService. Same reasoning as Payment. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'type'        => VoucherType::class,
            'status'      => VoucherStatus::class,
            'value'       => 'decimal:2',
            'valid_from'  => 'date',
            'expires_at'  => 'date',
            'redeemed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** The visit that EARNED this, for café credit. */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** The visit this was SPENT on, for a gift voucher. */
    public function redeemedForReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'redeemed_for_reservation_id');
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function redeemedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', VoucherStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()))
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', now()));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($term) {
            $inner->where('code', 'like', $term . '%')
                ->orWhere('issued_to_name', 'like', '%' . $term . '%')
                ->orWhere('issued_to_email', 'like', '%' . $term . '%')
                ->orWhereHas('reservation', fn (Builder $r) => $r->where('reference_code', 'like', $term . '%'));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    /**
     * Expiry is derived, never stored.
     *
     * True the moment the day passes, whether or not any job ran. A status
     * column would need a nightly task to stay honest, and the first time that
     * task failed the studio would be accepting coupons it believed were dead.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->endOfDay()->isPast();
    }

    /** Issued, but the visit it belongs to has not happened yet. */
    public function notYetValid(): bool
    {
        return $this->valid_from !== null && $this->valid_from->startOfDay()->isFuture();
    }

    public function isRedeemable(): bool
    {
        return $this->status === VoucherStatus::Active
            && ! $this->hasExpired()
            && ! $this->notYetValid();
    }

    /**
     * The label a human should see, which is not the same as the status.
     *
     * An Active voucher three months past its date is Active in the database
     * and Expired to everybody looking at it. Showing the raw status here would
     * have staff honouring coupons that are not good.
     */
    public function displayStatus(): string
    {
        if ($this->status === VoucherStatus::Active && $this->hasExpired()) {
            return 'Expired';
        }

        if ($this->status === VoucherStatus::Active && $this->notYetValid()) {
            return 'Not yet valid';
        }

        return $this->status->label();
    }

    public function displayColour(): string
    {
        if ($this->status === VoucherStatus::Active && $this->hasExpired()) {
            return 'danger';
        }

        if ($this->status === VoucherStatus::Active && $this->notYetValid()) {
            return 'warning';
        }

        return $this->status->colour();
    }

    /*
    |--------------------------------------------------------------------------
    | Editing and deleting
    |--------------------------------------------------------------------------
    | Both answer with a REASON rather than a bare boolean, for the same reason
    | unusableReason() does: the policy needs a yes or no to draw a button, and
    | the person who clicked needs a sentence when the answer changed underneath
    | them. One rule, stated once, read by the policy, the service and the view.
    */

    /**
     * Why this cannot be edited, or null if it can.
     *
     * Café credit is excluded because it is not a thing anybody decided — it is
     * arithmetic on a paid visit, and a hand-edited value would no longer match
     * the workshop rate that produced it. A redeemed voucher is excluded
     * because it has already been honoured at the value it had.
     */
    public function uneditableReason(): ?string
    {
        if ($this->type->isAutomatic()) {
            return 'Café credit is worked out from the visit that earned it and is not edited by hand.';
        }

        if ($this->status === VoucherStatus::Redeemed) {
            return 'This has already been used. A spent voucher stays as it was spent.';
        }

        if ($this->status === VoucherStatus::Cancelled) {
            return 'This voucher was cancelled. Editing it would not bring it back.';
        }

        return null;
    }

    public function isEditable(): bool
    {
        return $this->uneditableReason() === null;
    }

    /**
     * Why this cannot be deleted, or null if it can.
     *
     * Deliberately narrower than cancelling. A cancelled voucher CAN be deleted
     * — it is already dead and the row is only clutter — but a redeemed one
     * never can, because that row is the record of money the studio gave away.
     */
    public function undeletableReason(): ?string
    {
        if ($this->type->isAutomatic()) {
            return 'Café credit belongs to the visit that earned it. Cancel it instead — deleting the row would let a repeated payment callback issue it a second time.';
        }

        if ($this->status === VoucherStatus::Redeemed) {
            return 'This voucher was spent. Deleting it would erase the record of it.';
        }

        return null;
    }

    public function isDeletable(): bool
    {
        return $this->undeletableReason() === null;
    }

    /**
     * Why this cannot be used, in words a member of staff can read out.
     *
     * Returns null when it can. Deliberately specific — "expired on 14 August"
     * ends a conversation at the counter that "invalid voucher" would prolong.
     */
    public function unusableReason(): ?string
    {
        if ($this->status === VoucherStatus::Redeemed) {
            return 'This was already used on ' . $this->redeemed_at?->format('j M Y') . '.';
        }

        if ($this->status === VoucherStatus::Cancelled) {
            return 'This voucher was cancelled.';
        }

        if ($this->hasExpired()) {
            return 'This expired on ' . $this->expires_at->format('j M Y') . '.';
        }

        if ($this->notYetValid()) {
            return 'This is not valid until ' . $this->valid_from->format('j M Y') . '.';
        }

        return null;
    }
}
