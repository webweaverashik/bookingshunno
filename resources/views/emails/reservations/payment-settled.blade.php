{{--
    INTERNAL. Goes to every active Admin, never to the visitor.

    Fires only when a payment request is fully settled — the client's "payment
    completed, i.e. reservation confirmed". A part payment does not reach here;
    see SendReservationNotifications::handlePaymentReceived().

    PRINTS THE RESERVATION'S ACTUAL STATUS rather than asserting it is
    confirmed. Money can land against a booking that was cancelled while the
    visitor was at the gateway, and that is the one case in this email worth
    catching quickly. Saying "Confirmed" unconditionally would hide exactly the
    situation an Admin needs to see.

    $payment and $transaction are both guaranteed by the caller. Guarded anyway:
    a template that fatals inside a queue worker fails silently.
--}}

@component('mail::message')
# Payment complete

@if ($payment)
BDT {{ number_format((float) $payment->amount_paid) }} has been received in full
against {{ $payment->reference }}@if ($transaction) by {{ $transaction->method->label() }}@endif.
@else
A payment request has been settled in full.
@endif

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

**Visitor:** {{ $reservation->user?->name }} &middot; {{ $reservation->user?->email }}@if ($reservation->user?->phone) &middot; {{ $reservation->user->phone }}@endif

**Reservation status:** {{ $reservation->status->label() }}

**Reservation total:** BDT {{ number_format($reservation->payableTotal() ) }}
&middot; **Paid to date:** BDT {{ number_format($reservation->amountPaid()) }}
&middot; **Outstanding:** BDT {{ number_format($reservation->outstandingTotal()) }}

@if ($transaction?->external_reference)
**Gateway reference:** {{ $transaction->external_reference }}
@endif

@if ($note)
@component('mail::panel')
{{ $note }}
@endcomponent
@endif

@component('mail::button', ['url' => route('admin.reservations.index', ['q' => $reservation->reference_code, 'status' => 'all', 'range' => 'all'])])
Open in the admin panel
@endcomponent

@component('mail::subcopy')
The visitor has been sent their receipt separately. If the status above is
anything other than Confirmed, this money arrived against a booking that is no
longer live and may need refunding.
@endcomponent
@endcomponent
