<head>
    <title>@yield('title', 'Dashboard') - BIDA CPF</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8" />
    <meta name="description"
        content="BIDA CPF Management System is an all-in-one solution for managing Contributory Provident Fund (CPF) operations at BIDA, streamlining contributions, advance management, interest distribution, and ledger maintenance." />
    <meta name="keywords"
        content="cpf management system, bida cpf, contributory provident fund, cpf operations, cpf administration, digital cpf management" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:title" content="BIDA CPF Management System - Complete CPF Management Solution">
    <meta property="og:url" content="https://ashikur-rahman.com" />
    <meta property="og:site_name" content="BIDA CPF Management System" />
    <link rel="canonical" href="https://ashikur-rahman.com" />
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
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    <script>
        // Frame-busting to prevent site from being loaded within a frame without permission (click-jacking) if (window.top != window.self) { window.top.location.replace(window.self.location.href); }
    </script>
</head>
