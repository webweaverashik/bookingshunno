{{--
    A reservation has been cancelled from this end.

    The reason is required, and is the most useful thing in the email. Whether
    the visitor is owed money depends on whether any had been taken, which is
    Phase 12/13 territory — so this says a refund will be arranged rather than
    stating an amount the system cannot yet be sure of.
--}}

@component('mail::message')
# Your reservation has been cancelled

Hello {{ $reservation->user?->name }},

We are writing to let you know that your reservation with
{{ config('app.name') }} has been cancelled.

@component('mail::panel')
{{ $note }}
@endcomponent

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

If you have already paid for this visit, we will be in touch about a refund. If
you would like to come another time, reply to this email and we will find a date
that works.

@component('mail::subcopy')
Your reference is {{ $reservation->reference_code }}.
@endcomponent

With apologies,
The {{ config('app.name') }} team
@endcomponent
