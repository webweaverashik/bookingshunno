<head>
    <title>@yield('title', 'Reservation') - Shunno Art Cafe</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8" />
    <meta name="description"
        content="Shunno Art Cafe Reservation System for managing visitor reservations, creative experiences, workshops, payments, and gift vouchers." />
    <meta name="keywords"
        content="Shunno Art Cafe, reservation system, art cafe reservation, workshop reservation, creative experience, visitor reservation" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Shunno Art Cafe Reservation" />
    <meta property="og:url" content="https://booking.studioshunno.net" />
    <meta property="og:site_name" content="Shunno Art Cafe Reservation" />
    <link rel="canonical" href="https://booking.studioshunno.net" />
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" />

    <!--begin::Fonts(mandatory for all pages)-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
    <!--end::Fonts-->

    <!--begin::Vendor Stylesheets(used for this page only)-->
    @stack('page-css')
    <!--end::Vendor Stylesheets-->

    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="{{ asset('assets/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->

    {{--
        The frame-busting script that was here has been removed.

        It ran `if (window.top != window.self) window.top.location.replace(...)`,
        which is defeated by one attribute: an attacker frames the panel with
        <iframe sandbox="allow-scripts">, the script is allowed to run but
        forbidden from navigating the top window, the assignment fails silently,
        and the page renders inside the frame with the defence appearing to be
        present.

        App\Http\Middleware\SecurityHeaders now sends X-Frame-Options: DENY and
        a CSP frame-ancestors directive on every web response — public pages
        included, which this script never covered. The browser refuses to build
        the frame before any of our code runs, and no sandbox attribute can
        switch that off.

        Do not add a JavaScript frame-buster back. If framing ever needs to be
        allowed somewhere, the exemption belongs in that middleware, where it is
        one list in one file.
    --}}

</head>
