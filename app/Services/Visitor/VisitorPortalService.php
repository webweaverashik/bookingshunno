<?php

namespace App\Services\Visitor;

use App\Enums\Reservation\ReservationStatus;
use App\Enums\Voucher\VoucherType;
use App\Models\Auth\User;
use App\Models\Reservation\Reservation;
use App\Models\Voucher\Voucher;
use Illuminate\Support\Collection;

/**
 * Everything a visitor is allowed to see about themselves.
 *
 * A service rather than queries in the controller for one specific reason: the
 * ownership filter. Every read in the visitor area is scoped to one user_id,
 * and that scoping has to be impossible to forget. Putting it in one class
 * means there is exactly one line to get right, instead of one per controller
 * method and one more each time a method is added.
 *
 * Nothing here takes an id from the request. The user comes from the session,
 * always.
 */
class VisitorPortalService
{
    /**
     * The visitor's reservations, split into what is still ahead of them and
     * what is not.
     *
     * "Ahead" means a future date AND a booking that is still alive. A request
     * declined for next Tuesday is chronologically upcoming and practically
     * over, and listing it under "coming up" would have somebody turning up.
     *
     * @return array{upcoming: Collection<int,Reservation>, past: Collection<int,Reservation>}
     */
    public function visits(User $user): array
    {
        $reservations = Reservation::query()
            ->where('user_id', $user->id)
            ->with([
                'items.workshop',
                'payments.transactions',
                'purposes',
            ])
            ->orderByDesc('reserved_date')
            ->orderByDesc('id')
            ->get();

        $dead = [
            ReservationStatus::Declined,
            ReservationStatus::Cancelled,
            ReservationStatus::Completed,
            ReservationStatus::NoShow,
        ];

        $today = now()->startOfDay();

        $upcoming = $reservations->filter(
            fn (Reservation $r) => $r->reserved_date->gte($today) && ! in_array($r->status, $dead, true)
            // Soonest first: the next visit is the one being looked for.
        )->sortBy(fn (Reservation $r) => $r->reserved_date->timestamp)->values();

        $past = $reservations->reject(
            fn (Reservation $r) => $upcoming->contains(fn (Reservation $u) => $u->id === $r->id)
        )->values();

        return ['upcoming' => $upcoming, 'past' => $past];
    }

    /**
     * Codes belonging to this person.
     *
     * Two ways to belong, because vouchers arrive by two routes. Café credit is
     * attached to the reservation that earned it and carries no address of its
     * own worth trusting; a gift voucher was issued by hand to an email and may
     * have no reservation at all. Either link counts.
     *
     * The reservation subquery is written out rather than taken from
     * $user->reservations(), whose relation carries a latest() ordering that
     * has no business inside a WHERE IN.
     *
     * @return Collection<int,Voucher>
     */
    public function vouchers(User $user): Collection
    {
        $owned = Reservation::where('user_id', $user->id)->select('id');

        return Voucher::query()
            ->with(['reservation', 'workshop'])
            ->where(function ($query) use ($owned, $user) {
                $query->whereIn('reservation_id', $owned)
                    ->orWhere('issued_to_email', $user->email);
            })
            /*
             | Usable ones first, then by soonest expiry. Somebody opening this
             | page is looking for a code to read out at the counter, not for an
             | archive of ones they have already spent.
             */
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw('expires_at IS NULL, expires_at ASC')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Vouchers this one visit produced.
     *
     * Queried rather than read from a relation on purpose: Reservation has no
     * vouchers() relation, and adding one to a Phase 4 model to serve a Phase 15
     * page is the kind of change §24 warns about. One indexed lookup on a
     * detail page costs nothing.
     *
     * @return Collection<int,Voucher>
     */
    public function creditFrom(Reservation $reservation): Collection
    {
        return Voucher::where('reservation_id', $reservation->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The single sentence a visitor actually wants: what happens next, and
     * whether it is waiting on them.
     *
     * Derived from status rather than stored, so it cannot drift from the
     * booking. `owed` is what drives the highlighted band at the top of the
     * page — it is true only when the next move is the visitor's.
     *
     * @return array{owed: bool, title: string, body: string, cta: ?array{label:string,url:string}}
     */
    public function nextStep(Reservation $reservation): array
    {
        $payment = $reservation->openPayment();

        return match ($reservation->status) {
            ReservationStatus::Pending, ReservationStatus::Escalated => [
                'owed' => false,
                'title' => 'With us for review',
                'body' => 'Someone from the studio is looking at your request. We usually reply within a day, and nothing is owed until we do.',
                'cta' => null,
            ],

            ReservationStatus::InfoRequested => [
                'owed' => true,
                'title' => 'We need a little more from you',
                'body' => 'Check the email we sent — there is something we need to know before we can confirm this. Replying to that email is the quickest way.',
                'cta' => null,
            ],

            ReservationStatus::Approved => [
                'owed' => false,
                'title' => 'Approved — a payment link is coming',
                'body' => 'Your date is held. We will email you a link to settle it shortly, and it will appear here too.',
                'cta' => null,
            ],

            ReservationStatus::PaymentRequested => [
                'owed' => true,
                'title' => 'Ready for payment',
                'body' => $payment?->due_at
                    ? 'Please settle this by '.$payment->due_at->format('j F, g:i A').' so we can hold your place.'
                    : 'Settle this and your visit is confirmed.',
                'cta' => $payment
                    ? ['label' => 'Pay now', 'url' => route('payment.portal', $payment->token)]
                    : null,
            ],

            ReservationStatus::Confirmed => [
                'owed' => false,
                'title' => 'Confirmed — we will see you then',
                'body' => 'Nothing more to do. Come a few minutes early if you can; aprons and everything else are here.',
                'cta' => null,
            ],

            ReservationStatus::Completed => [
                'owed' => false,
                'title' => 'Thank you for coming',
                'body' => 'We hope it was a good evening. Any café credit from this visit is listed below.',
                'cta' => null,
            ],

            ReservationStatus::Declined => [
                'owed' => false,
                'title' => 'We could not take this one',
                'body' => 'The email we sent explains why. Do send another request — a different date often works.',
                'cta' => null,
            ],

            ReservationStatus::Cancelled => [
                'owed' => false,
                'title' => 'Cancelled',
                'body' => 'This booking is closed. If that is not right, please get in touch.',
                'cta' => null,
            ],

            ReservationStatus::NoShow => [
                'owed' => false,
                'title' => 'Marked as missed',
                'body' => 'We had you down for this one but did not see you. If something went wrong, please tell us.',
                'cta' => null,
            ],
        };
    }

    /**
     * A short line of encouragement at the top of the page, and the honest
     * count behind it. Read from the columns Phase 8 already maintains rather
     * than counted here, so this page and the admin visitor profile agree.
     *
     * @return array{visits:int, credit:float}
     */
    public function summary(User $user, Collection $vouchers): array
    {
        return [
            'visits' => (int) ($user->total_reservations ?? 0),

            // Spendable café credit only. A redeemed or expired coupon is not
            // money and must not be added up as if it were.
            'credit' => (float) $vouchers
                ->filter(fn (Voucher $v) => $v->type === VoucherType::CafeCredit && $v->isRedeemable())
                ->sum(fn (Voucher $v) => (float) $v->value),
        ];
    }
}
