{{--
    The payment link.

    Everything the brief's §20 asks for is here — reference, date, time,
    experience, reservation total, payment type, amount required, amount already
    paid, remaining amount, deadline and link — but written as a person asking
    another person for money, not as an invoice. This is a small studio.

    $payment is guaranteed by ReservationMailKind::PaymentRequested; the
    listener never sends this kind without one.
--}}

@component('mail::message')
# Please complete your payment

Hello {{ $reservation->user?->name }},

Your visit is approved. Here is what we have booked, and what is left to do:

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

@include('emails.partials.payment-summary', ['payment' => $payment])

@component('mail::button', ['url' => route('payment.portal', $payment->token)])
Pay now
@endcomponent

Please pay by **{{ $payment->due_at->format('l, j F, g:i A') }}**. We hold your slot until then.

@if ($payment->type->leavesBalance())
The remaining BDT {{ number_format($payment->remainingOnReservation()) }} is payable at the studio
on the day.
@endif

@if ($note)
@component('mail::panel')
{{ $note }}
@endcomponent
@endif

If anything about the date, the time or the number of people has changed, reply to this email
before you pay and we will sort it out.

@component('mail::subcopy')
Your reservation reference is {{ $reservation->reference_code }} and this payment is
{{ $payment->reference }}. The payment link is personal to you — please do not forward it.
@endcomponent

Thank you,
The {{ config('app.name') }} team
@endcomponent
