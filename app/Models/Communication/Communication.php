<?php

namespace App\Models\Communication;

use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\ReservationMailKind;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentTransaction;
use App\Models\Reservation\Reservation;

/**
 * One email we tried to send.
 *
 * Written by CommunicationLogger and updated by LogMailDelivery. Nothing else
 * should touch it: this is a record of something that happened, and a row that
 * can be edited from a form is not evidence of anything.
 */
class Communication extends Model
{
    use HasFactory;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status'    => CommunicationStatus::class,
            'is_resend' => 'boolean',
            'queued_at' => 'datetime',
            'sent_at'   => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * The enum case, where the string still maps to one.
     *
     * Nullable rather than cast, because kind is a plain string column: an
     * email kind removed in a later phase should leave its old log rows
     * readable rather than throwing on every render.
     */
    public function mailKind(): ?ReservationMailKind
    {
        return $this->kind ? ReservationMailKind::tryFrom($this->kind) : null;
    }

    /**
     * Whether this message can be sent again.
     *
     * Internal mail is excluded on purpose. Escalation notices go to every
     * Admin and carry a Manager's private note about a booking; a resend button
     * on one invites somebody to fire staff-only correspondence at whoever
     * happens to be in the To field.
     */
    public function isResendable(): bool
    {
        $kind = $this->mailKind();

        return $kind !== null
            && ! $kind->isInternal()
            && $this->reservation_id !== null;
    }

    /**
     * A resend is throttled per ORIGINAL message, not per staff member.
     *
     * The thing being protected is the visitor's inbox, and it does not care
     * which member of staff clicked. Five minutes is long enough that a
     * double-click or an impatient second attempt does nothing, and short
     * enough that somebody genuinely fixing a typo'd address is not stuck
     * waiting.
     */
    public function canResendNow(): bool
    {
        if (! $this->isResendable()) {
            return false;
        }

        $last = static::query()
            ->where('resend_of', $this->resend_of ?? $this->id)
            ->latest('created_at')
            ->value('created_at');

        return $last === null || $last->lt(now()->subMinutes(5));
    }
}
