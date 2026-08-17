@extends('layouts.app')

@section('title', 'Settings')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">Settings</h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Settings</li>
        </ul>
    </div>
@endsection

@section('content')

    {{--
        FIVE TABS, FIVE FORMS, FIVE SAVE BUTTONS.

        Not one form with one Save. A studio phone number and an SMTP port have
        nothing to do with each other, and a single form means a validation
        error on the mail tab blocks saving the phone number. Each pane posts to
        its own endpoint and reports its own result.

        Bootstrap's own tab markup, no JavaScript of ours involved in the
        switching — Metronic's bundle handles data-bs-toggle="tab".
    --}}
    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-6 fw-semibold mb-6" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#settings-general" role="tab">
                <i class="ki-outline ki-shop fs-4 me-2"></i>Studio
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#settings-reservations" role="tab">
                <i class="ki-outline ki-calendar-8 fs-4 me-2"></i>Reservations
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#settings-payments" role="tab">
                <i class="ki-outline ki-dollar fs-4 me-2"></i>Payments
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#settings-mail" role="tab">
                <i class="ki-outline ki-sms fs-4 me-2"></i>Email
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#settings-gateway" role="tab">
                <i class="ki-outline ki-credit-cart fs-4 me-2"></i>Payment gateway
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="settings-general" role="tabpanel">
            @include('admin.settings.partials.general')
        </div>
        <div class="tab-pane fade" id="settings-reservations" role="tabpanel">
            @include('admin.settings.partials.reservations')
        </div>
        <div class="tab-pane fade" id="settings-payments" role="tabpanel">
            @include('admin.settings.partials.payments')
        </div>
        <div class="tab-pane fade" id="settings-mail" role="tabpanel">
            @include('admin.settings.partials.mail')
        </div>
        <div class="tab-pane fade" id="settings-gateway" role="tabpanel">
            @include('admin.settings.partials.gateway')
        </div>
    </div>
@endsection

@push('page-js')
    <script>
        var SettingsConfig = {
            testMailUrl: "{{ route('admin.settings.mail.test') }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/settings.js') }}"></script>
@endpush
