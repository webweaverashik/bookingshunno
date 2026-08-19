{{--
    The nudge before a payment deadline.

    Shorter than payment-requested on purpose. The visitor has already had the
    full breakdown once; repeating every figure would make this read as a second
    bill rather than a reminder, and the thing they need is the deadline and the
    button.

    NO PRESSURE LANGUAGE. No "final notice", no "act now", no countdown. This is
    an artist-run studio reminding somebody about an evening they wanted to
    come to — and the honest position is that if they miss it, they can ask
    again. Saying so costs the studio nothing and is the difference between a
    reminder and a demand.

    $payment is guaranteed by ReservationMailKind::PaymentReminder; the command
    never sends this kind without one.
--}}

@component('mail::message')
# A quick reminder

Hello {{ $reservation->user?->name }},

We are holding your place, and the payment link we sent you is still open —
but not for much longer.

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

@include('emails.partials.payment-summary', ['payment' => $payment])

@component('mail::button', ['url' => route('payment.portal', $payment->token)])
Complete your payment
@endcomponent

The link closes on **{{ $payment->due_at->format('l, j F, g:i A') }}**.

If the date no longer works, or something has changed about the number of
people coming, just reply to this email — we would much rather move it than
lose you. And if the link does close before you get to it, that is not the end
of things either; write to us and we will look at what is still free.

@component('mail::subcopy')
Your reservation reference is {{ $reservation->reference_code }}. If you have already paid,
please ignore this — receipts can take a few minutes to catch up.
@endcomponent
@endcomponent
