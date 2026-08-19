{{--
    The visitor's payment page.

    Standalone rather than inside the public layout: somebody arriving here has
    one thing to do, and a site header offering them the landing page and the
    reservation popup is an invitation to wander off mid-payment.

    Two ways to settle, per the client: pay online, or redeem a voucher. There
    is deliberately NO manual-payment panel — no bank details, no bKash number.
    Staff record offline payments from the admin panel when they take them;
    publishing an account number here would invite visitors to transfer money
    that nobody is watching for.

    The head, the footer and the reference never change, so they live here.
    Everything that a voucher redemption can alter lives in the body partial,
    which the server re-renders and the script swaps in whole.
--}}

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Complete your payment — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/payment-portal.css') }}">
</head>

<body>
    <main class="pay">

        <header class="pay-head">
            {{-- Logo with the studio name as its alt text, so a blocked or
                 missing image degrades to the name rather than to nothing. --}}
            <img src="{{ asset('img/shunno-logo.png') }}" alt="{{ config('app.name') }}" class="pay-logo">
            <div class="pay-ref">{{ $payment->reference }}</div>
        </header>

        <div data-pay-body>
            @include('public.partials.payment-body')
        </div>

        <footer class="pay-foot">
            <p>
                {{ config('app.name') }} &middot; {{ $contact['address'] ?? '' }}<br>
                {{ $contact['phone'] ?? '' }} &middot; {{ $contact['email'] ?? '' }}
            </p>
            <p class="pay-foot__small">
                This link is personal to your reservation. Please do not forward it.
            </p>
        </footer>
    </main>

    {{-- Progressive enhancement only. Every form below works without it. --}}
    <script src="{{ asset('js/payment-portal.js') }}" defer></script>
</body>

</html>
