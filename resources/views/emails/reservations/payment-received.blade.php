{{--
    The receipt — and, when the request settles, the confirmation.

    ONE email rather than the two the brief lists. "Payment Confirmation" and
    "Final Reservation Confirmation" would fire in the same second and say the
    same thing, and two messages about one event teaches people to stop opening
    either. A genuine pre-visit reminder, sent a day or two before the date with
    directions and what to bring, is worth having and is a different email at a
    different time.

    Wording forks on whether this settled the request. A part payment that said
    "your visit is confirmed" would be a lie the visitor acts on.
--}}

@component('mail::message')
@if ($transaction?->settledInFull())
# Payment received — you are confirmed
@else
# Part payment received
@endif

Hello {{ $reservation->user?->name }},

Thank you — we have received **BDT {{ number_format((float) ($transaction?->amount ?? $payment->amount_paid)) }}**
@if ($transaction)
by {{ $transaction->method->label() }} on {{ $transaction->received_at->format('j F Y') }}.
@endif

@if ($transaction?->settledInFull())
Your reservation is confirmed. We look forward to seeing you.
@else
BDT {{ number_format((float) $transaction->balance_after) }} remains on this payment request.
Your reservation is confirmed once that reaches us.
@endif

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

@include('emails.partials.payment-summary', ['payment' => $payment])

@if ($transaction)
@component('mail::button', ['url' => route('payslip', ['token' => $payment->token, 'transaction' => $transaction->reference])])
View your payslip
@endcomponent
@endif

@if (! $transaction?->settledInFull())
@component('mail::button', ['url' => route('payment.portal', $payment->token), 'color' => 'success'])
Pay the balance
@endcomponent
@endif

@if ($payment->type->leavesBalance() && $transaction?->settledInFull())
The remaining BDT {{ number_format($payment->remainingOnReservation()) }} for your visit is payable
at the studio on the day.
@endif

@component('mail::subcopy')
Receipt {{ $transaction?->reference }} against payment {{ $payment->reference }},
for reservation {{ $reservation->reference_code }}.
Find us at {{ config('shunno.contact.address') }}.
@endcomponent

Thank you,
The {{ config('app.name') }} team
@endcomponent
