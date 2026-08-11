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
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..600&family=Karla:ital,wght@0,300..700;1,400&family=Noto+Sans+Bengali:wght@400;600&display=swap" rel="stylesheet">

    @vite(['resources/scss/public.scss', 'resources/js/public.js'])
    @stack('styles')
</head>
<body>
    <a class="sh-skip" href="#main">Skip to content</a>

    <x-public.nav />

    <main id="main">
        @yield('content')
    </main>

    <x-public.footer />

    @stack('scripts')
</body>
</html>
