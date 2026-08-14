{{--
    The studio cannot take this booking.

    $note is required by ReservationDecisionRequest and is shown as the reason.
    A rejection without one is not something this system makes easy to send.

    Deliberately no figures anywhere in this email. Repeating a price to someone
    who has just been told no reads as a sales pitch.
--}}

@component('mail::message')
# About your request

Hello {{ $reservation->user?->name }},

Thank you for wanting to visit {{ config('app.name') }}. We are sorry to say we
cannot take this booking.

@component('mail::panel')
{{ $note }}
@endcomponent

Nothing has been charged. If another date might work, we would genuinely like to
hear from you — reply to this email, or send us a new request through the
website.

@component('mail::subcopy')
Your reference is {{ $reservation->reference_code }}, for
{{ $reservation->reserved_date->format('j F Y') }}.
@endcomponent

With thanks,
The {{ config('app.name') }} team
@endcomponent
