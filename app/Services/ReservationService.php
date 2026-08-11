<?php

namespace App\Services;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
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
     * password_set_at stays null until they deliberately choose one.
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
