<?php

namespace App\Models;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_column(ReservationStatus::open(), 'value'));
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Pending);
    }

    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('reserved_date', $date);
    }

    /**
     * Seats already committed on a date. Anything still open counts — a slot
     * held for someone awaiting payment is not free.
     */
    public function scopeHoldingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Approved->value,
            ReservationStatus::PaymentRequested->value,
            ReservationStatus::Confirmed->value,
        ]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ReservationStatus::open(), true);
    }
}
