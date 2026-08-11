<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>

{{-- Admin is a private tool: keep it out of search results entirely. --}}
<meta name="robots" content="noindex, nofollow">
<meta name="description" content="Reservation administration for {{ config('app.name') }}.">

<link rel="icon" href="{{ asset('img/shunno-logo.png') }}" type="image/png">

{{--
    Clickjacking protection lives in App\Http\Middleware\SecurityHeaders, not
    here. The frame-busting script this template used to carry had its entire
    body after a `//` on one line, so it never executed.
--}}

<link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css">
<link href="{{ asset('css/admin.css') }}" rel="stylesheet" type="text/css">

@stack('styles')
