<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Shunno Art Cafe — Visits by reservation')</title>
    <meta name="description" content="@yield('description', 'Shunno Art Cafe is an artist-run studio and evening cafe in Lalmatia, Dhaka. Clay, print and paint sessions by reservation.')">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Shunno Art Cafe">
    <meta property="og:title" content="@yield('title', 'Shunno Art Cafe — Visits by reservation')">
    <meta property="og:description" content="@yield('description', 'An artist-run studio and evening cafe in Lalmatia, Dhaka. Visits are arranged by prior reservation.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('img/shunno-logo.png') }}">
    <meta name="twitter:card" content="summary">

    <link rel="icon" href="{{ asset('img/shunno-logo.png') }}" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..600&family=Karla:ital,wght@0,300..700;1,400&family=Noto+Sans+Bengali:wght@400;600&display=swap"
        rel="stylesheet">

    {{-- No build step: plain CSS and JS served straight from public/.
         See docs/NO-BUILD-STEP.md for why Bootstrap and npm were dropped. --}}
    <link rel="stylesheet" href="{{ asset('css/public.css') }}">
    @stack('styles')
</head>

<body>
    <a class="sh-skip" href="#main">Skip to content</a>

    <x-public.nav />

    <main id="main">
        @yield('content')
    </main>

    <x-public.reservation-modal />

    <button class="sh-totop" id="sh-totop" type="button" aria-label="Back to top">
        <svg class="sh-totop__ring" viewBox="0 0 58 58" aria-hidden="true">
            <circle class="bg" cx="29" cy="29" r="27"></circle>
            <circle class="fg" cx="29" cy="29" r="27"></circle>
        </svg>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
            stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V5M5 12l7-7 7 7" />
        </svg>
    </button>

    <x-public.footer />

    <script src="{{ asset('js/public.js') }}" defer></script>
    <script src="{{ asset('js/datepicker.js') }}" defer></script>
    <script src="{{ asset('js/reservation.js') }}" defer></script>
    @stack('scripts')
</body>

</html>
