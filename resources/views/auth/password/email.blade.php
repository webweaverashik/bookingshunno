@extends('auth.app')

@section('title', 'Forgot password')

@section('content')
    <form class="form w-100" method="POST" action="{{ route('password.email') }}" id="kt_password_reset_form" novalidate>
        @csrf

        <div class="text-center mb-10">
            <h1 class="text-dark fw-bolder mb-3">Forgot your password?</h1>
            <div class="text-gray-500 fw-semibold fs-6">
                Enter your email and we'll send you a link to set a new one.
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="fv-row mb-8">
            <label class="form-label fw-bold text-gray-900 fs-6" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control bg-transparent @error('email') is-invalid @enderror" required>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-3">
            <button type="submit" id="kt_password_reset_submit" class="btn btn-primary">
                <span class="indicator-label">Send link</span>
                <span class="indicator-progress">
                    Please wait… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
            <a href="{{ route('login') }}" class="btn btn-light">Back to sign in</a>
        </div>
    </form>
@endsection

@push('auth-scripts')
    <script src="{{ asset('js/auth/password/email.js') }}"></script>
@endpush
