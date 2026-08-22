{{--
    VISITOR-FACING. Sent once, when an Admin marks the visit Completed.

    Completed is a closed status and the last thing anyone can do to a
    reservation, so this is the last email in the thread. It says thank you and
    stops. Deliberately NOT a status notification: "your reservation has been
    marked completed" is how the database would put it, not how a studio would.

    NO COUPON CODE HERE, on purpose. Café credit is issued and emailed when the
    payment settles, which can be weeks earlier, and the code lives in that
    message. Reprinting it would mean two live emails carrying the same
    redeemable code, and the wrong one being read out at the counter. This
    points at the original instead.

    $note is whatever the Admin typed when completing. Usually nothing.
--}}

@component('mail::message')
# Thank you for visiting

Hello {{ $reservation->user?->name }},

It was good to have you at {{ config('app.name') }}. We hope the time here was
worth it.

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

@if ($note)
@component('mail::panel')
{{ $note }}
@endcomponent
@endif

If your visit came with a café coupon, it is active now — the code is in the
email we sent when your payment cleared, and it can be spent on food and drinks
at the counter.

We would be glad to hear what you thought. Reply to this email, or tell us in
person next time — and if you would like to come back, you can request another
visit whenever you are ready.

@component('mail::subcopy')
Your reference was {{ $reservation->reference_code }}.
Find us at {{ config('shunno.contact.address') }}.
@endcomponent

With thanks,
The {{ config('app.name') }} team
@endcomponent
