@extends('auth.app')

@section('title', 'Verify')

@section('content')
    <form class="form w-100" method="POST" action="{{ route('otp.verify') }}" id="kt_otp_form" novalidate>
        @csrf

        <div class="text-center mb-10">
            <img alt="" class="mh-75px mb-6 rounded-circle" src="{{ asset('img/shunno-logo.png') }}">
            <h1 class="text-dark fw-bolder mb-3">Check your email</h1>
            <div class="text-muted fw-semibold fs-6">
                We sent a {{ config('otp.length') }}-digit code to <strong>{{ $email }}</strong>
            </div>
        </div>

        <input type="hidden" name="code" value="">

        <div class="mb-10 px-md-2">
            <div class="d-flex flex-wrap flex-stack gap-2">
                @for ($i = 0; $i < config('otp.length', 6); $i++)
                    <input type="text" inputmode="numeric" maxlength="1" data-otp-digit
                           class="form-control bg-transparent h-60px w-50px fs-2qx text-center"
                           autocomplete="one-time-code" aria-label="Digit {{ $i + 1 }}">
                @endfor
            </div>
            @error('code')
                <div class="invalid-feedback d-block text-center mt-3">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-grid mb-6">
            <button type="submit" id="kt_otp_submit" class="btn btn-primary">
                <span class="indicator-label">Verify</span>
                <span class="indicator-progress">
                    Please wait… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </form>

    <div class="text-center">
        <div class="text-muted fs-7 mb-2" id="kt_otp_timer"></div>

        <form method="POST" action="{{ route('otp.resend') }}" class="d-inline">
            @csrf
            <button type="submit" id="kt_otp_resend" class="btn btn-link"
                    data-wait="{{ $secondsUntilResend }}">
                Send another code
            </button>
        </form>

        <div class="mt-4">
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <a href="{{ route('login') }}" class="text-muted fs-7">Use a different account</a>
            </form>
        </div>
    </div>
@endsection

@push('auth-scripts')
    <script src="{{ asset('js/auth/otp.js') }}"></script>
@endpush
