{{--
    The coupon lands in the visitor's inbox.

    Serves both types. The wording forks on what the voucher can be spent on,
    because telling somebody their café credit can go towards a workshop would
    be a promise the checkout then refuses.

    The code is the whole point of the email, so it is large, on its own line,
    and repeated in the subcopy — visitors forward these, screenshot them, and
    read them out over the phone.
--}}

@component('mail::message')
@if ($voucher->type === \App\Enums\Voucher\VoucherType::CafeCredit)
# Here is your café credit
@else
# You have a gift voucher
@endif

Hello {{ $voucher->issued_to_name ?? 'there' }},

@if ($voucher->type === \App\Enums\Voucher\VoucherType::CafeCredit)
Thank you for booking with us. Your visit comes with
**BDT {{ number_format((float) $voucher->value) }}** to spend on food and drink at the café.
@else
Someone has given you **BDT {{ number_format((float) $voucher->value) }}** to spend at
{{ config('app.name') }}.
@endif

@component('mail::panel')
### {{ $voucher->code }}
Worth BDT {{ number_format((float) $voucher->value) }}
@endcomponent

@component('mail::table')
|              |                                                                    |
|:-------------|-------------------------------------------------------------------:|
| Value        | BDT {{ number_format((float) $voucher->value) }}                    |
@if ($voucher->valid_from)
| Valid from   | {{ $voucher->valid_from->format('j F Y') }}                         |
@endif
@if ($voucher->expires_at)
| Valid until  | {{ $voucher->expires_at->format('j F Y') }}                         |
@endif
| Spend it on  | {{ $voucher->type->spendableOn() }}                                 |
@endcomponent

@if ($voucher->type === \App\Enums\Voucher\VoucherType::CafeCredit)
Just show this code at the counter when you visit. It can be used once, in one go, so pick
something you will enjoy.

@if ($voucher->valid_from)
It becomes usable on the day of your visit and runs until
{{ $voucher->expires_at?->format('j F Y') }}.
@endif
@else
Quote this code when you reserve, or enter it at the payment stage. It can be used once.

@if ($voucher->workshop)
This one is for **{{ $voucher->workshop->title }}**.
@endif
@endif

@component('mail::subcopy')
Your code is {{ $voucher->code }}. Keep this email — you will need the code.
Find us at {{ config('shunno.contact.address') }}.
@endcomponent

See you soon,
The {{ config('app.name') }} team
@endcomponent
