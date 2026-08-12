<!DOCTYPE html>
<html lang="en" direction="ltr" data-bs-theme="light">
<head>
    @include('layouts.partials.head')
</head>

<body id="kt_body" class="app-blank">

<div class="d-flex flex-column flex-root" id="kt_app_root">
    <div class="d-flex flex-column flex-lg-row flex-column-fluid">

        {{-- Left: the form --}}
        <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
            <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                <div class="w-100 w-md-400px">
                    @yield('content')
                </div>
            </div>
        </div>

        {{-- Right: brand panel. Copy replaced; the BIDA original described a
             provident fund management system. --}}
        <div class="d-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-1 order-lg-2"
             style="background-color: #241C17;">
            <div class="d-flex flex-column flex-center py-15 px-5 px-md-15 w-100">
                <img class="mx-auto w-100px mb-10 rounded-circle" src="{{ asset('img/shunno-logo.png') }}" alt="">
                <h1 class="text-white fs-2qx fw-bold text-center mb-4">Shunno Art Cafe</h1>
                <p class="text-center" style="color:#C7B9AE; max-width: 34ch;">
                    Reservation administration. Every request is read by a person before anything
                    is charged &mdash; this is where that happens.
                </p>
            </div>
        </div>
    </div>
</div>

@include('layouts.partials.scripts')
@stack('auth-scripts')
</body>
</html>
