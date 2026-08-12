@extends('auth.app')

@section('title', 'Set a new password')

@section('content')
    <form class="form w-100" method="POST" action="{{ route('password.update') }}" id="kt_new_password_form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="text-center mb-10">
            <h1 class="text-dark fw-bolder mb-3">Set a new password</h1>
            <div class="text-gray-500 fw-semibold fs-6">At least eight characters.</div>
        </div>

        <div class="fv-row mb-8">
            <label class="form-label fw-bold text-gray-900 fs-6" for="email">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                   class="form-control bg-transparent @error('email') is-invalid @enderror" required>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="fv-row mb-5">
            <label class="form-label fw-bold text-gray-900 fs-6" for="password">New password</label>
            <input type="password" id="password" name="password" autocomplete="new-password"
                   class="form-control bg-transparent @error('password') is-invalid @enderror" required>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="fv-row mb-8">
            <label class="form-label fw-bold text-gray-900 fs-6" for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   autocomplete="new-password" class="form-control bg-transparent" required>
        </div>

        <div class="d-grid mb-10">
            <button type="submit" id="kt_new_password_submit" class="btn btn-primary">
                <span class="indicator-label">Save password</span>
                <span class="indicator-progress">
                    Please wait… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </form>
@endsection

@push('auth-scripts')
    <script src="{{ asset('js/auth/password/reset.js') }}"></script>
@endpush
