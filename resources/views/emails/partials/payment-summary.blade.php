{{--
    The brief's payment summary block, line for line and in its order:
    reservation total, payment type, payment required, amount paid, remaining.

    A partial rather than repeated markup because it appears in both payment
    emails and the two must never disagree about a figure. Reads the payment's
    SNAPSHOT columns, not the live reservation — the visitor was quoted these,
    and a later price correction must not silently rewrite what they were told.
--}}

@component('mail::table')
|                        |                                                                   |
|:-----------------------|------------------------------------------------------------------:|
| Reservation total      | BDT {{ number_format((float) $payment->reservation_total) }}       |
| Payment type           | {{ $payment->type->describe((int) $payment->percentage) }}         |
| **Payment required**   | **BDT {{ number_format((float) $payment->amount_due) }}**          |
@if ((float) $payment->amount_paid > 0)
| Already received       | BDT {{ number_format((float) $payment->amount_paid) }}             |
| **Still to pay**       | **BDT {{ number_format($payment->outstanding()) }}**               |
@endif
@if ($payment->type->leavesBalance())
| Payable at the studio  | BDT {{ number_format($payment->remainingOnReservation()) }}        |
@endif
@endcomponent
