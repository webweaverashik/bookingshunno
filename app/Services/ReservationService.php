<?php

namespace App\Services;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Auth\User;
use App\Models\VisitPurpose;
use App\Models\Workshop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class ReservationService
{
    public function __construct(private readonly PricingService $pricing)
    {
    }

    /**
     * Turn a validated public request into a reservation.
     *
     * Everything runs in one transaction: a visitor with no reservation, or a
     * reservation with no line item, is worse than a failed submission.
     *
     * @param  array<string,mixed>  $data
     */
    public function createFromPublicRequest(array $data, ?string $ip = null): Reservation
    {
        $workshop = Workshop::active()->where('slug', $data['experience'])->firstOrFail();

        return DB::transaction(function () use ($data, $workshop, $ip) {
            $user    = $this->resolveVisitor($data);
            $pricing = $this->pricing->forWorkshop($workshop, (int) $data['participants']);

            $start = $data['time'];
            $end   = date('H:i', strtotime($start) + $workshop->duration_minutes * 60);

            $reservation = new Reservation([
                'reference_code'   => $this->generateReference(),
                'user_id'          => $user->id,
                'reserved_date'    => $data['date'],
                'start_time'       => $start,
                'end_time'         => $end,
                'participants'     => (int) $data['participants'],
                'special_requests' => $data['notes'] ?? null,
                'source'           => ReservationSource::Web,
                'submitted_ip'     => $ip,
            ]);

            // Guarded columns: set here, never mass-assigned from the form.
            $reservation->status          = ReservationStatus::Pending;
            $reservation->subtotal        = $pricing['subtotal'];
            $reservation->discount_amount = $pricing['discount'];
            $reservation->total_amount    = $pricing['total'];
            $reservation->discount_reason = $pricing['discount_reason'];
            $reservation->save();

            $reservation->items()->create([
                'workshop_id'      => $workshop->id,
                'title_snapshot'   => $workshop->title,
                'unit_price'       => $workshop->price,
                'quantity'         => (int) $data['participants'],
                'line_total'       => $pricing['subtotal'],
                'duration_minutes' => $workshop->duration_minutes,
            ]);

            if (! empty($data['purposes'])) {
                $reservation->purposes()->sync(
                    VisitPurpose::whereIn('slug', $data['purposes'])->pluck('id')
                );
            }

            $reservation->statusHistory()->create([
                'from_status' => null,
                'to_status'   => ReservationStatus::Pending,
                'changed_by'  => null,          // submitted by the visitor
                'note'        => 'Request submitted from the website.',
            ]);

            $user->increment('total_reservations');
            $user->forceFill(['last_reservation_at' => now()])->save();

            // PHASE 11: event(new ReservationRequested($reservation)) sends the
            // acknowledgement email. Nothing is emailed yet.

            return $reservation->fresh(['items', 'purposes', 'user']);
        });
    }

    /**
     * PHASE 9 — correct the details of a reservation without changing its
     * status.
     *
     * Three things have to move together and none of them is obvious from the
     * call site, which is why this is not left to the controller:
     *
     *   1. end_time is derived from the workshop's duration, so a new start
     *      time that does not also move the end leaves a booking that occupies
     *      the wrong window in every capacity query.
     *   2. The line item carries its own quantity and line_total. Changing
     *      participants on the reservation alone would leave the item — which
     *      is what a receipt is built from — disagreeing with the total.
     *   3. Every change is written into the status history. The table is
     *      append-only and from_status equals to_status here, which reads
     *      correctly as "nothing moved, but someone touched this".
     *
     * @param  array<string,mixed>  $changes
     */
    public function amend(
        Reservation $reservation,
        array $changes,
        User $actor,
        ?string $note = null,
        bool $overrode = false,
    ): Reservation {
        return DB::transaction(function () use ($reservation, $changes, $actor, $note, $overrode) {
            $item     = $reservation->items()->first();
            $workshop = $item?->workshop;

            $summary = [];

            // Notes are editable at every status; the visit fields are not, and
            // the caller has already established which case this is by what it
            // put in $changes.
            if (array_key_exists('special_requests', $changes)) {
                if ((string) $reservation->special_requests !== (string) $changes['special_requests']) {
                    $summary[] = 'notes';
                }

                $reservation->special_requests = $changes['special_requests'];
            }

            if (isset($changes['reserved_date'])) {
                $was = $reservation->reserved_date?->toDateString();

                if ($was !== $changes['reserved_date']) {
                    $summary[] = 'date ' . $was . ' to ' . $changes['reserved_date'];
                }

                $reservation->reserved_date = $changes['reserved_date'];
            }

            if (isset($changes['start_time'])) {
                $was = substr((string) $reservation->start_time, 0, 5);

                if ($was !== $changes['start_time']) {
                    $summary[] = 'time ' . $was . ' to ' . $changes['start_time'];
                }

                $reservation->start_time = $changes['start_time'];

                // Duration comes from the item's snapshot, not the live
                // workshop: if the client shortened the session last week, this
                // booking was still sold at the old length.
                $minutes = (int) ($item?->duration_minutes ?: $workshop?->duration_minutes ?: 0);

                $reservation->end_time = $minutes > 0
                    ? date('H:i', strtotime($changes['start_time']) + $minutes * 60)
                    : $reservation->end_time;
            }

            if (isset($changes['participants'])) {
                $was = (int) $reservation->participants;
                $now = (int) $changes['participants'];

                if ($was !== $now) {
                    $summary[] = "party of {$was} to {$now}";
                }

                $reservation->participants = $now;

                // Re-priced from the item's own unit price, so a change to the
                // workshop's price since this was booked does not silently
                // reprice a request the visitor has already seen a figure for.
                $unit    = (float) ($item?->unit_price ?? 0);
                $pricing = $this->pricing->calculate($unit, $now);

                $reservation->subtotal        = $pricing['subtotal'];
                $reservation->discount_amount = $pricing['discount'];
                $reservation->total_amount    = $pricing['total'];
                $reservation->discount_reason = $pricing['discount_reason'];

                $item?->update([
                    'quantity'   => $now,
                    'line_total' => $pricing['subtotal'],
                ]);
            }

            $reservation->save();

            // Nothing actually changed — no history row. An audit trail full of
            // "opened and closed the form" entries is an audit trail nobody
            // reads.
            if ($summary === [] && $note === null) {
                return $reservation->fresh(['items', 'purposes', 'user', 'statusHistory.changedBy']);
            }

            $line = $summary === []
                ? 'Edited.'
                : 'Edited: ' . implode('; ', $summary) . '.';

            if ($overrode) {
                $line .= ' Availability rules overridden.';
            }

            if ($note) {
                $line .= ' ' . $note;
            }

            $reservation->statusHistory()->create([
                'from_status' => $reservation->status,
                'to_status'   => $reservation->status,
                'changed_by'  => $actor->id,
                'note'        => $line,
            ]);

            return $reservation->fresh(['items', 'purposes', 'user', 'statusHistory.changedBy']);
        });
    }

    /**
     * Move a reservation to a new status, refusing transitions the lifecycle
     * does not allow, and recording who did it.
     */
    public function transition(
        Reservation $reservation,
        ReservationStatus $to,
        ?User $actor = null,
        ?string $note = null,
    ): Reservation {
        $from = $reservation->status;

        if ($from === $to) {
            return $reservation;
        }

        if (! $from->canTransitionTo($to)) {
            throw new RuntimeException(
                "A reservation cannot go from {$from->label()} to {$to->label()}."
            );
        }

        return DB::transaction(function () use ($reservation, $from, $to, $actor, $note) {
            $reservation->status = $to;

            match ($to) {
                ReservationStatus::Approved  => $reservation->forceFill([
                    'approved_at' => now(),
                    'approved_by' => $actor?->id,
                ]),
                ReservationStatus::Declined  => $reservation->forceFill(['declined_at' => now()]),
                ReservationStatus::Confirmed => $reservation->forceFill(['confirmed_at' => now()]),
                ReservationStatus::Cancelled => $reservation->forceFill(['cancelled_at' => now()]),
                default                      => $reservation,
            };

            $reservation->save();

            $reservation->statusHistory()->create([
                'from_status' => $from,
                'to_status'   => $to,
                'changed_by'  => $actor?->id,
                'note'        => $note,
            ]);

            return $reservation;
        });
    }

    /**
     * Find the visitor by email, or create one.
     *
     * New visitors get an unusable random password rather than NULL: the auth
     * guard's handling of a null hash has changed between Laravel versions and
     * is not something a payment portal should depend on. They sign in by OTP;
     */
    private function resolveVisitor(array $data): User
    {
        $user = User::withTrashed()->firstWhere('email', $data['email']);

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            // Keep the most recent contact details, but never overwrite a name
            // or number with an empty one.
            $user->fill(array_filter([
                'name'  => $data['name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]))->save();

            if (! $user->hasRole(User::ROLE_VISITOR) && ! $user->isStaff()) {
                $user->assignRole(User::ROLE_VISITOR);
            }

            return $user;
        }

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'],
            'whatsapp'  => $data['phone'],
            'password'  => Hash::make(Str::random(64)),
            'is_active' => true,
            'source'    => ReservationSource::Web,
        ]);

        $user->assignRole(User::ROLE_VISITOR);

        return $user;
    }

    /**
     * SHN-2608-A7K3. Ambiguous characters are excluded so a reference read out
     * over the phone survives the trip.
     */
    private function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // no I, O, 0, 1

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'SHN-' . now()->format('ym') . '-' . $suffix;
        } while (Reservation::withTrashed()->where('reference_code', $code)->exists());

        return $code;
    }
}
