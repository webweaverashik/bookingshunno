@extends('layouts.app')

@section('title', 'My profile')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">My profile</h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">My profile</li>
        </ul>
    </div>
@endsection

@section('content')

    {{-- A summary strip, then three panes. Everything on this page is about the
         SIGNED-IN user and nothing takes an id — see ProfileController. --}}
    <div class="card mb-6">
        <div class="card-body d-flex align-items-center flex-wrap gap-4 py-6">
            <span class="symbol symbol-60px">
                <span class="symbol-label bg-light-primary text-primary fs-2 fw-bold">
                    {{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}
                </span>
            </span>

            <div class="flex-grow-1 min-w-0">
                <div class="fs-4 fw-bold text-gray-900" id="profile-name">{{ $user->name }}</div>
                <div class="text-muted fs-7" id="profile-email">{{ $user->email }}</div>

                {{-- PHASE 19 — when this account was created. Both forms shown
                     on purpose: the exact date is what goes in a support
                     conversation, and "2 years ago" is what makes it mean
                     something at a glance. --}}
                <div class="text-gray-500 fs-8 mt-1">
                    <i class="ki-outline ki-calendar-8 fs-7 me-1"></i>
                    Account created {{ $user->created_at?->format('j F Y') }}
                    @if ($user->created_at)
                        <span class="text-gray-400">&middot; {{ $user->created_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-column align-items-end gap-2">
                @foreach ($user->getRoleNames() as $role)
                    <span class="badge badge-light-primary">{{ $role }}</span>
                @endforeach

                @if ($user->last_reservation_at)
                    <span class="text-muted fs-8">Last booking
                        {{ $user->last_reservation_at->format('j M Y') }}</span>
                @endif
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-6 fw-semibold mb-6" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#profile-details" role="tab">
                <i class="ki-outline ki-user fs-4 me-2"></i>Your details
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#profile-password" role="tab">
                <i class="ki-outline ki-lock-2 fs-4 me-2"></i>Password
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#profile-activity" role="tab">
                <i class="ki-outline ki-time fs-4 me-2"></i>Sign-in history
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="profile-details" role="tabpanel">
            @include('admin.profile.partials.details')
        </div>
        <div class="tab-pane fade" id="profile-password" role="tabpanel">
            @include('admin.profile.partials.password')
        </div>
        <div class="tab-pane fade" id="profile-activity" role="tabpanel">
            @include('admin.profile.partials.activity')
        </div>
    </div>
@endsection

{{-- DataTables, for the sign-in history only. It is bundled with Metronic, so
     nothing new is installed. --}}
@push('page-css')
    <link href="{{ asset('assets/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet"
        type="text/css" />
@endpush

@push('vendor-js')
    <script src="{{ asset('assets/plugins/custom/datatables/datatables.bundle.js') }}"></script>
@endpush

@push('page-js')
    <script>
        var ProfileConfig = {
            updateUrl: "{{ route('admin.profile.update') }}",
            passwordUrl: "{{ route('admin.profile.password') }}",
            currentEmail: @json($user->email)
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/profile.js') }}"></script>
@endpush
