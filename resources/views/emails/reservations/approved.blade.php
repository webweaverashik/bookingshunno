{{--
    The visit has been approved.

    PHASE 12 REPLACES THE "what happens next" SECTION. There is no payment link
    yet, so this email must not promise one that does not work — it says
    payment details are coming separately, which is true today. When Phase 12
    lands, the payment request becomes its own email and this section should
    point at it explicitly rather than being vague.
--}}

@component('mail::message')
# Your visit is approved

Hello {{ $reservation->user?->name }},

We would be glad to have you. Here is what we have booked for you:

@include('emails.partials.reservation-summary', ['reservation' => $reservation])

**Total: BDT {{ number_format($reservation->payableTotal()) }}**
@if ($reservation->hasManualPrice())
{{ $reservation->total_override_reason }}
@elseif ((float) $reservation->discount_amount > 0)
This includes the group discount.
@endif

@if ($note)
@component('mail::panel')
{{ $note }}
@endcomponent
@endif

## What happens next

We will be in touch shortly with payment details. Your visit is confirmed once
that payment reaches us.

If anything about the date, the time or the number of people has changed, reply
to this email and we will sort it out before you pay.

@component('mail::subcopy')
Your reference is {{ $reservation->reference_code }}.
Find us at {{ config('shunno.contact.address') }}.
@endcomponent

We look forward to seeing you,
The {{ config('app.name') }} team
@endcomponent
