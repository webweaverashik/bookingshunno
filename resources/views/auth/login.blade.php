@extends('auth.app')

@section('title', 'Sign in')

@section('content')
    <form class="form w-100" method="POST" action="{{ route('login') }}" id="kt_sign_in_form" novalidate>
        @csrf

        <div class="text-center mb-11">
            <h1 class="text-dark fw-bolder mb-3">Sign in</h1>
            <div class="text-gray-500 fw-semibold fs-6">Shunno reservation administration</div>
        </div>

        <div class="fv-row mb-8">
            <label class="form-label fs-6 fw-bold text-dark" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control bg-transparent @error('email') is-invalid @enderror"
                   autocomplete="username" required>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="fv-row mb-3">
            <label class="form-label fs-6 fw-bold text-dark" for="password">Password</label>
            <div class="position-relative">
                <input type="password" id="password" name="password"
                       class="form-control bg-transparent @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                <span class="btn btn-sm btn-icon position-absolute translate-middle top-50 end-0 me-2"
                      data-kt-password-toggle>
                    <i class="ki-outline ki-eye-slash fs-2"></i>
                </span>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
            <div></div>
            <a href="{{ route('password.request') }}" class="link-primary">Forgot password?</a>
        </div>

        <div class="d-grid mb-10">
            <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                <span class="indicator-label">Continue</span>
                <span class="indicator-progress">
                    Please wait… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>

        @if (config('otp.staff.enabled'))
            <div class="text-gray-500 text-center fw-semibold fs-7">
                We'll email you a {{ config('otp.length') }}-digit code to finish signing in.
            </div>
        @endif
    </form>
@endsection

@push('auth-scripts')
    <script src="{{ asset('js/auth/login.js') }}"></script>
@endpush
