{{--
    The studio needs something from the visitor before it can decide.

    $note is the message the admin typed, and it is the entire reason this email
    exists — ReservationDecisionRequest makes it required for exactly that
    reason. Everything else here is framing.
--}}

@component('mail::message')
# One quick thing

Hello {{ $reservation->user?->name }},

Thank you for your request to visit {{ config('app.name') }}. Before we confirm
it, we need to ask you something:

@component('mail::panel')
{{ $note }}
@endcomponent

Just reply to this email and let us know. Your request is being held while we
wait, so nothing is lost.

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

@component('mail::subcopy')
Your reference is {{ $reservation->reference_code }}. Nothing has been charged,
and your date is not held until the visit is confirmed.
@endcomponent

Warmly,
The {{ config('app.name') }} team
@endcomponent
