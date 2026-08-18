<?php

namespace App\Models\Payment;

use App\Enums\Payment\PaymentChannel;
use App\Enums\Payment\PaymentMethod;
use App\Enums\Payment\TransactionStatus;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PHASE 12B — one settlement, and the payslip that documents it.
 *
 * Immutable by intent. Nothing in the application updates a transaction after
 * it is written: a receipt the visitor is holding must say the same thing next
 * month as it did today. A correction is a new row — a refund, when Phase 13
 * builds them — not an edit. That is also why balance_after is snapshotted here
 * rather than derived from the payment on render.
 */
class PaymentTransaction extends Model
{
    use HasFactory;

    /** Written only by PaymentService, explicitly. Same reasoning as Payment. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'channel'         => PaymentChannel::class,
            'status'          => TransactionStatus::class,
            'method'          => PaymentMethod::class,
            'amount'          => 'decimal:2',
            'balance_after'   => 'decimal:2',
            'gateway_payload' => 'array',
            'received_at'     => 'datetime',
            'validated_at'    => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * PHASE 13 — a settled attempt, as opposed to one still in flight or one
     * that failed. Only these render a payslip and only these moved money.
     */
    public function scopeReceipts(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', TransactionStatus::Success);
    }

    public function isReceipt(): bool
    {
        return $this->status === TransactionStatus::Success;
    }

    /**
     * Whether this receipt settled the request outright.
     *
     * Drives the payslip's wording: "paid in full" against "part payment
     * received, BDT X still to come" is the difference between a document a
     * visitor can file and one they need to act on.
     */
    public function settledInFull(): bool
    {
        // Null balance_after means an attempt that never settled, so it cannot
        // have settled anything in full. Phase 13 made the column nullable.
        return $this->balance_after !== null && (float) $this->balance_after < 0.01;
    }

    /** The payslip's own title. */
    public function title(): string
    {
        return $this->settledInFull() ? 'Payment receipt' : 'Part payment receipt';
    }
}
