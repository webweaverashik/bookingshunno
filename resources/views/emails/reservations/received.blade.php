{{--
    Sent the moment a request arrives from the website.

    The job of this email is to stop the visitor wondering. It confirms what we
    have, says plainly that nothing is booked yet and nothing has been charged,
    and gives a rough idea of when a person will get back to them.

    It states the estimated total but does NOT ask for money — payment only
    exists after approval, and hinting otherwise here would undo the whole
    point of the review workflow.
--}}

@component('mail::message')
# Thank you, {{ $reservation->user?->name ?: 'and welcome' }}

We have your request for a visit to {{ config('app.name') }}. Here is what you sent us:

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

@if ($reservation->special_requests)
**What you told us:** {{ $reservation->special_requests }}
@endif

**Estimated total: BDT {{ number_format($reservation->payableTotal()) }}**
@if ((float) $reservation->discount_amount > 0)
This includes the group discount.
@endif

## What happens next

This is a request, not a booking. Someone here reads every one of them — we are
a small studio and we would rather understand what you are coming for than have
a calendar fill itself. You will hear back from us within a day or so.

Nothing has been charged, and your date is not held until we have confirmed it.
If we approve your visit we will write again with payment details.

@component('mail::subcopy')
If any of the details above are wrong, just reply to this email and tell us —
your reference is {{ $reservation->reference_code }}.
@endcomponent

Warmly,
The {{ config('app.name') }} team
@endcomponent
