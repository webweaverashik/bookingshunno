{{--
    INTERNAL. Goes to every active Admin and Manager, never to the visitor.

    The visitor gets emails.reservations.received at the same moment. This is
    the studio's copy and reads studio-to-studio: it carries the phone number,
    the visitor's own words and a link straight into the panel, because the
    point of it is that somebody can act without hunting.

    NOT an escalation. Nothing has gone wrong and nobody is blocked — this is
    simply the queue getting one longer. The wording stays flat on purpose;
    an alert that shouts at every request stops being read by the tenth.
--}}

@component('mail::message')
# A new reservation request

{{ $reservation->user?->name ?? 'Someone' }} has asked to visit. It is sitting
in the review queue.

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

**Visitor:** {{ $reservation->user?->name }} &middot; {{ $reservation->user?->email }}@if ($reservation->user?->phone) &middot; {{ $reservation->user->phone }}@endif

**Total as it stands:** BDT {{ number_format($reservation->payableTotal()) }}@if ((float) $reservation->discount_amount > 0) (includes the group discount)@endif

@if ($reservation->special_requests)
@component('mail::panel')
{{ $reservation->special_requests }}
@endcomponent
@endif

@component('mail::button', ['url' => route('admin.reservations.index', ['q' => $reservation->reference_code, 'status' => 'all', 'range' => 'all'])])
Open in the admin panel
@endcomponent

@component('mail::subcopy')
You are receiving this because you hold a staff role. The visitor has been sent
an acknowledgement and is waiting on a decision — nothing has been promised to
them yet.
@endcomponent
@endcomponent
